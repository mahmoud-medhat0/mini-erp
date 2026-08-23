<?php

namespace App\Application\FixedAssets;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Domain\Audit\AuditLogger;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\FixedAssetDisposal;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedAssetDisposalPostingService
{
    public function __construct(
        private readonly PostingEngine $postingEngine,
        private readonly PeriodGuard $periodGuard,
        private readonly AccountingAccountMappingService $accountMappingService,
        private readonly ReversalService $reversalService,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly NumberSequenceAllocator $numberSequenceAllocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Preview calculation for disposing an asset.
     */
    public function previewDisposal(string $fixedAssetId, string $disposalDate, string $disposalType, int $proceedsMinor = 0): array
    {
        /** @var FixedAsset $asset */
        $asset = FixedAsset::query()->findOrFail($fixedAssetId);

        $this->assertAssetCanBeDisposed($asset);
        $this->assertValidDisposalType($disposalType);

        if ($disposalType !== 'sale') {
            $proceedsMinor = 0;
        }

        $this->assertValidProceeds($proceedsMinor);

        $schedules = FixedAssetDepreciationSchedule::query()
            ->where('fixed_asset_id', $asset->id)
            ->orderBy('period_number')
            ->get();

        $this->assertNoUnresolvedDepreciationAroundDisposalDate($schedules, $disposalDate);

        $postedAccumulatedMinor = $this->postedAccumulatedDepreciationMinor($asset, $schedules);

        $nbvMinor = max(0, (int) $asset->cost_minor - $postedAccumulatedMinor);

        $gainMinor = 0;
        $lossMinor = 0;

        if ($proceedsMinor > $nbvMinor) {
            $gainMinor = $proceedsMinor - $nbvMinor;
        } elseif ($proceedsMinor < $nbvMinor) {
            $lossMinor = $nbvMinor - $proceedsMinor;
        }

        return [
            'fixed_asset_id' => $asset->id,
            'asset_number' => $asset->asset_number,
            'cost_minor' => $asset->cost_minor,
            'posted_accumulated_depreciation_minor' => $postedAccumulatedMinor,
            'net_book_value_minor' => $nbvMinor,
            'disposal_type' => $disposalType,
            'proceeds_minor' => $proceedsMinor,
            'gain_minor' => $gainMinor,
            'loss_minor' => $lossMinor,
        ];
    }

    /**
     * Post disposal transaction for a fixed asset.
     */
    public function postDisposal(
        string $fixedAssetId,
        string $disposalDate,
        string $disposalType,
        int $proceedsMinor = 0,
        ?int $userId = null,
        ?string $idempotencyKey = null
    ): FixedAssetDisposal {
        $normalizedProceedsMinor = $disposalType === 'sale' ? $proceedsMinor : 0;
        $reversedDisposalCount = FixedAssetDisposal::query()
            ->where('fixed_asset_id', $fixedAssetId)
            ->where('status', 'reversed')
            ->count();

        $idempotencyKey ??= implode(':', [
            'fixed_asset_disposal',
            $fixedAssetId,
            'reversed',
            $reversedDisposalCount,
            $disposalDate,
            $disposalType,
            $normalizedProceedsMinor,
        ]);

        $result = $this->idempotencyStore->run(
            operation: 'fixed_asset_disposal.post',
            rawKey: $idempotencyKey,
            callback: function () use ($fixedAssetId, $disposalDate, $disposalType, $proceedsMinor, $userId): FixedAssetDisposal {
                return DB::transaction(function () use ($fixedAssetId, $disposalDate, $disposalType, $proceedsMinor, $userId): FixedAssetDisposal {
                    /** @var FixedAsset $asset */
                    $asset = FixedAsset::query()->lockForUpdate()->findOrFail($fixedAssetId);

                    $this->assertAssetCanBeDisposed($asset);
                    $this->assertValidDisposalType($disposalType);

                    if ($disposalType !== 'sale') {
                        $proceedsMinor = 0;
                    }

                    $this->assertValidProceeds($proceedsMinor);

                    // Lock and resolve open period
                    $period = $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($disposalDate);

                    $schedules = FixedAssetDepreciationSchedule::query()
                        ->where('fixed_asset_id', $asset->id)
                        ->orderBy('period_number')
                        ->lockForUpdate()
                        ->get();

                    $this->assertNoUnresolvedDepreciationAroundDisposalDate($schedules, $disposalDate);

                    $postedAccumulatedMinor = $this->postedAccumulatedDepreciationMinor($asset, $schedules);

                    $nbvMinor = max(0, (int) $asset->cost_minor - $postedAccumulatedMinor);

                    $gainMinor = 0;
                    $lossMinor = 0;

                    if ($proceedsMinor > $nbvMinor) {
                        $gainMinor = $proceedsMinor - $nbvMinor;
                    } elseif ($proceedsMinor < $nbvMinor) {
                        $lossMinor = $nbvMinor - $proceedsMinor;
                    }

                    // Resolve GL mapping accounts
                    $costAccount = $this->accountMappingService->getAccount('fixed_asset_cost');
                    $accumAccount = $this->accountMappingService->getAccount('accumulated_depreciation');

                    $gainAccount = null;
                    if ($gainMinor > 0) {
                        $gainAccount = $this->accountMappingService->getAccount('fixed_asset_disposal_gain');
                    }

                    $lossAccount = null;
                    if ($lossMinor > 0) {
                        $lossAccount = $this->accountMappingService->getAccount('fixed_asset_disposal_loss');
                    }

                    $clearingAccount = null;
                    if ($proceedsMinor > 0) {
                        $clearingAccount = $this->accountMappingService->getAccount('fixed_asset_clearing');
                    }

                    // Number allocation
                    $dispSeq = $this->numberSequenceAllocator->nextValue('fixed_asset_disposal');
                    $dispNumber = sprintf('DISP-%s-%05d', Carbon::parse($disposalDate)->format('Y'), $dispSeq);

                    $sourceId = (string) Str::uuid();

                    // Construct draft JournalEntry
                    $journal = new JournalEntry([
                        'id' => (string) Str::uuid(),
                        'entry_date' => $disposalDate,
                        'financial_period_id' => $period->id,
                        'source_type' => 'fixed_asset_disposal',
                        'source_id' => $sourceId,
                        'status' => 'approved',
                        'description' => "fixed_asset.disposal:{$dispNumber}",
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                    $journal->save();

                    $lineNo = 1;

                    // Line 1 (Debit): Accumulated Depreciation
                    if ($postedAccumulatedMinor > 0) {
                        JournalLine::query()->create([
                            'id' => (string) Str::uuid(),
                            'journal_entry_id' => $journal->id,
                            'line_no' => $lineNo++,
                            'account_id' => $accumAccount->id,
                            'memo' => "fixed_asset.disposal.accumulated:{$dispNumber}",
                            'debit_minor' => $postedAccumulatedMinor,
                            'credit_minor' => 0,
                            'currency' => $asset->currency,
                            'fx_rate_e6' => 1000000,
                            'debit_txn_minor' => $postedAccumulatedMinor,
                            'credit_txn_minor' => 0,
                        ]);
                    }

                    // Line 2 (Debit): Proceeds Clearing
                    if ($proceedsMinor > 0 && $clearingAccount) {
                        JournalLine::query()->create([
                            'id' => (string) Str::uuid(),
                            'journal_entry_id' => $journal->id,
                            'line_no' => $lineNo++,
                            'account_id' => $clearingAccount->id,
                            'memo' => "fixed_asset.disposal.proceeds:{$dispNumber}",
                            'debit_minor' => $proceedsMinor,
                            'credit_minor' => 0,
                            'currency' => $asset->currency,
                            'fx_rate_e6' => 1000000,
                            'debit_txn_minor' => $proceedsMinor,
                            'credit_txn_minor' => 0,
                        ]);
                    }

                    // Line 3 (Debit): Loss on Disposal
                    if ($lossMinor > 0 && $lossAccount) {
                        JournalLine::query()->create([
                            'id' => (string) Str::uuid(),
                            'journal_entry_id' => $journal->id,
                            'line_no' => $lineNo++,
                            'account_id' => $lossAccount->id,
                            'memo' => "fixed_asset.disposal.loss:{$dispNumber}",
                            'debit_minor' => $lossMinor,
                            'credit_minor' => 0,
                            'currency' => $asset->currency,
                            'fx_rate_e6' => 1000000,
                            'debit_txn_minor' => $lossMinor,
                            'credit_txn_minor' => 0,
                        ]);
                    }

                    // Line 4 (Credit): Fixed Asset Cost
                    JournalLine::query()->create([
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journal->id,
                        'line_no' => $lineNo++,
                        'account_id' => $costAccount->id,
                        'memo' => "fixed_asset.disposal.cost:{$dispNumber}",
                        'debit_minor' => 0,
                        'credit_minor' => $asset->cost_minor,
                        'currency' => $asset->currency,
                        'fx_rate_e6' => 1000000,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $asset->cost_minor,
                    ]);

                    // Line 5 (Credit): Gain on Disposal
                    if ($gainMinor > 0 && $gainAccount) {
                        JournalLine::query()->create([
                            'id' => (string) Str::uuid(),
                            'journal_entry_id' => $journal->id,
                            'line_no' => $lineNo++,
                            'account_id' => $gainAccount->id,
                            'memo' => "fixed_asset.disposal.gain:{$dispNumber}",
                            'debit_minor' => 0,
                            'credit_minor' => $gainMinor,
                            'currency' => $asset->currency,
                            'fx_rate_e6' => 1000000,
                            'debit_txn_minor' => 0,
                            'credit_txn_minor' => $gainMinor,
                        ]);
                    }

                    // Post journal voucher
                    $postedJournal = $this->postingEngine->post($journal, $userId ?? 0, allowControlAccounts: true);

                    // Create disposal record
                    $disposal = FixedAssetDisposal::query()->create([
                        'id' => $sourceId,
                        'number' => $dispNumber,
                        'fixed_asset_id' => $asset->id,
                        'disposal_date' => $disposalDate,
                        'financial_period_id' => $period->id,
                        'disposal_type' => $disposalType,
                        'proceeds_minor' => $proceedsMinor,
                        'net_book_value_minor' => $nbvMinor,
                        'gain_minor' => $gainMinor,
                        'loss_minor' => $lossMinor,
                        'status' => 'posted',
                        'journal_entry_id' => $postedJournal->id,
                        'posted_at' => now(),
                        'posted_by' => $userId,
                        'lock_version' => 0,
                    ]);

                    // Mark asset disposed
                    $asset->update([
                        'status' => 'disposed',
                        'lock_version' => $asset->lock_version + 1,
                        'updated_by' => $userId,
                    ]);

                    // Skip unposted future depreciation schedules for this asset
                    FixedAssetDepreciationSchedule::query()
                        ->where('fixed_asset_id', $asset->id)
                        ->where('status', 'planned')
                        ->where('period_end_date', '>=', $disposalDate)
                        ->update(['status' => 'skipped']);

                    if ($userId) {
                        $this->auditLogger->record(
                            $userId,
                            'fixed_asset_disposal.create',
                            'fixed_asset_disposal',
                            $disposal->id,
                            after: $disposal->toArray()
                        );
                    }

                    return $disposal;
                });
            },
            actorId: $userId
        );

        $val = $result->value;
        $dispId = is_array($val) ? $val['id'] : $val->id;

        return FixedAssetDisposal::query()->with(['asset', 'financialPeriod', 'journalEntry'])->findOrFail($dispId);
    }

    /**
     * Reverse a posted fixed asset disposal.
     */
    public function reverseDisposal(string $disposalId, ?int $userId = null): FixedAssetDisposal
    {
        return DB::transaction(function () use ($disposalId, $userId): FixedAssetDisposal {
            /** @var FixedAssetDisposal $disposal */
            $disposal = FixedAssetDisposal::query()->lockForUpdate()->findOrFail($disposalId);

            if ($disposal->status === 'reversed') {
                return $disposal;
            }

            /** @var FixedAsset $asset */
            $asset = FixedAsset::query()->lockForUpdate()->findOrFail($disposal->fixed_asset_id);

            if (! $disposal->journal_entry_id) {
                throw ValidationException::withMessages([
                    'disposal' => ['Disposal has no linked journal entry to reverse.'],
                ]);
            }

            $journal = JournalEntry::query()->findOrFail($disposal->journal_entry_id);
            $reversalPeriod = $this->periodGuard->resolveOpenPeriodForPostingDateWithLock(now()->toDateString());

            $reversalJournal = $this->reversalService->reverse(
                entry: $journal,
                reversalPeriodId: $reversalPeriod->id,
                userId: $userId ?? 0
            );

            $disposal->update([
                'status' => 'reversed',
                'reversal_journal_entry_id' => $reversalJournal->id,
                'lock_version' => $disposal->lock_version + 1,
            ]);

            // Restore asset status to active
            $asset->update([
                'status' => 'active',
                'lock_version' => $asset->lock_version + 1,
                'updated_by' => $userId,
            ]);

            // Restore skipped schedules back to planned
            FixedAssetDepreciationSchedule::query()
                ->where('fixed_asset_id', $asset->id)
                ->where('status', 'skipped')
                ->where('period_end_date', '>=', $disposal->disposal_date)
                ->update(['status' => 'planned']);

            if ($userId) {
                $this->auditLogger->record(
                    $userId,
                    'fixed_asset_disposal.reverse',
                    'fixed_asset_disposal',
                    $disposal->id,
                    after: $disposal->toArray()
                );
            }

            return $disposal->fresh(['asset', 'financialPeriod', 'journalEntry', 'reversalJournalEntry']);
        });
    }

    private function assertAssetCanBeDisposed(FixedAsset $asset): void
    {
        if ($asset->status !== 'active') {
            throw ValidationException::withMessages([
                'fixed_asset_id' => ["Only active assets can be disposed. Current status: [{$asset->status}]."],
            ]);
        }
    }

    private function assertValidDisposalType(string $disposalType): void
    {
        if (! in_array($disposalType, ['sale', 'scrap', 'retirement'], true)) {
            throw ValidationException::withMessages([
                'disposal_type' => ['Disposal type must be sale, scrap, or retirement.'],
            ]);
        }
    }

    private function assertValidProceeds(int $proceedsMinor): void
    {
        if ($proceedsMinor < 0) {
            throw ValidationException::withMessages([
                'proceeds_minor' => ['Proceeds amount cannot be negative.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, FixedAssetDepreciationSchedule>  $schedules
     */
    private function assertNoUnresolvedDepreciationAroundDisposalDate(Collection $schedules, string $disposalDate): void
    {
        $postedAfterDisposal = $schedules->first(
            fn (FixedAssetDepreciationSchedule $schedule): bool => $schedule->status === 'posted'
                && Carbon::parse($schedule->period_end_date)->greaterThan(Carbon::parse($disposalDate))
        );

        if ($postedAfterDisposal) {
            throw ValidationException::withMessages([
                'disposal_date' => ['Cannot dispose an asset before already posted depreciation schedule periods. Reverse those depreciation runs first.'],
            ]);
        }

        $unpostedBeforeDisposal = $schedules->first(
            fn (FixedAssetDepreciationSchedule $schedule): bool => $schedule->status === 'planned'
                && Carbon::parse($schedule->period_end_date)->lessThan(Carbon::parse($disposalDate))
        );

        if ($unpostedBeforeDisposal) {
            throw ValidationException::withMessages([
                'disposal_date' => ['Prior depreciation schedule periods must be posted or resolved before disposal.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, FixedAssetDepreciationSchedule>  $schedules
     */
    private function postedAccumulatedDepreciationMinor(FixedAsset $asset, Collection $schedules): int
    {
        return (int) $schedules
            ->where('status', 'posted')
            ->sum('depreciation_minor') + (int) $asset->opening_accumulated_depreciation_minor;
    }
}
