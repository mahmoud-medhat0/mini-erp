<?php

namespace App\Application\FixedAssets;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Domain\Audit\AuditLogger;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Numbering\NumberSequenceAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedAssetDepreciationPostingService
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
     * Post period depreciation run for all planned schedule rows in the period.
     */
    public function postDepreciationRun(string $financialPeriodId, ?int $userId = null, ?string $idempotencyKey = null): FixedAssetDepreciationRun
    {
        $idempotencyKey ??= 'fixed_asset_depreciation_run:'.$financialPeriodId;

        $result = $this->idempotencyStore->run(
            operation: 'fixed_asset_depreciation_run.post',
            rawKey: $idempotencyKey,
            callback: function () use ($financialPeriodId, $userId): FixedAssetDepreciationRun {
                return DB::transaction(function () use ($financialPeriodId, $userId): FixedAssetDepreciationRun {
                    // Assert period is open with lock
                    $period = $this->periodGuard->assertPeriodOpenForPostingWithLock($financialPeriodId);

                    // Query unposted schedule lines for active assets in period
                    $schedules = FixedAssetDepreciationSchedule::query()
                        ->where('financial_period_id', $period->id)
                        ->where('status', 'planned')
                        ->whereHas('asset', function ($q) {
                            $q->where('status', 'active');
                        })
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($schedules->isEmpty()) {
                        $existingRun = FixedAssetDepreciationRun::query()
                            ->where('financial_period_id', $period->id)
                            ->where('status', 'posted')
                            ->first();

                        if ($existingRun) {
                            return $existingRun;
                        }

                        throw ValidationException::withMessages([
                            'financial_period_id' => ['No planned active depreciation schedules found for this period.'],
                        ]);
                    }

                    $totalDepreciationMinor = (int) $schedules->sum('depreciation_minor');
                    if ($totalDepreciationMinor <= 0) {
                        throw ValidationException::withMessages([
                            'financial_period_id' => ['Total depreciation amount for selected period must be greater than zero.'],
                        ]);
                    }

                    // Resolve GL mapping accounts
                    $expenseAccount = $this->accountMappingService->getAccount('depreciation_expense');
                    $accumulatedAccount = $this->accountMappingService->getAccount('accumulated_depreciation');

                    $runSeq = $this->numberSequenceAllocator->nextValue('fixed_asset_depreciation_run');
                    $runYear = Carbon::parse($period->end_date)->format('Y');
                    $runNumber = sprintf('DEP-%s-%05d', $runYear, $runSeq);

                    $sourceId = (string) Str::uuid();

                    // Create draft journal entry (PostingEngine will allocate JV number)
                    $journal = new JournalEntry([
                        'id' => (string) Str::uuid(),
                        'entry_date' => $period->end_date,
                        'financial_period_id' => $period->id,
                        'source_type' => 'fixed_asset_depreciation_run',
                        'source_id' => $sourceId,
                        'status' => 'approved',
                        'description' => "fixed_asset.depreciation_run:{$runNumber}",
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                    $journal->save();

                    JournalLine::query()->create([
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journal->id,
                        'line_no' => 1,
                        'account_id' => $expenseAccount->id,
                        'memo' => "fixed_asset.depreciation_run.expense:{$runNumber}",
                        'debit_minor' => $totalDepreciationMinor,
                        'credit_minor' => 0,
                        'currency' => 'EGP',
                        'fx_rate_e6' => 1000000,
                        'debit_txn_minor' => $totalDepreciationMinor,
                        'credit_txn_minor' => 0,
                    ]);

                    JournalLine::query()->create([
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journal->id,
                        'line_no' => 2,
                        'account_id' => $accumulatedAccount->id,
                        'memo' => "fixed_asset.depreciation_run.accumulated:{$runNumber}",
                        'debit_minor' => 0,
                        'credit_minor' => $totalDepreciationMinor,
                        'currency' => 'EGP',
                        'fx_rate_e6' => 1000000,
                        'debit_txn_minor' => 0,
                        'credit_txn_minor' => $totalDepreciationMinor,
                    ]);

                    // Post via PostingEngine
                    $postedJournal = $this->postingEngine->post($journal, $userId ?? 0, allowControlAccounts: true);

                    $run = FixedAssetDepreciationRun::query()->create([
                        'id' => $sourceId,
                        'number' => $runNumber,
                        'financial_period_id' => $period->id,
                        'run_date' => $period->end_date,
                        'total_depreciation_minor' => $totalDepreciationMinor,
                        'asset_count' => $schedules->pluck('fixed_asset_id')->unique()->count(),
                        'status' => 'posted',
                        'journal_entry_id' => $postedJournal->id,
                        'posted_at' => now(),
                        'posted_by' => $userId,
                    ]);

                    foreach ($schedules as $schedule) {
                        $schedule->update([
                            'status' => 'posted',
                            'depreciation_run_id' => $run->id,
                            'journal_entry_id' => $postedJournal->id,
                            'posted_at' => now(),
                            'posted_by' => $userId,
                        ]);
                    }

                    if ($userId) {
                        $this->auditLogger->record(
                            $userId,
                            'fixed_asset_depreciation_run.create',
                            'fixed_asset_depreciation_run',
                            $run->id,
                            after: $run->toArray()
                        );
                    }

                    return $run;
                });
            },
            actorId: $userId
        );

        $val = $result->value;
        $runId = is_array($val) ? $val['id'] : $val->id;

        return FixedAssetDepreciationRun::query()->with(['financialPeriod', 'journalEntry'])->findOrFail($runId);
    }

    /**
     * Reverse a posted depreciation run.
     */
    public function reverseDepreciationRun(string $runId, ?int $userId = null): FixedAssetDepreciationRun
    {
        return DB::transaction(function () use ($runId, $userId): FixedAssetDepreciationRun {
            /** @var FixedAssetDepreciationRun $run */
            $run = FixedAssetDepreciationRun::query()->lockForUpdate()->findOrFail($runId);

            if ($run->status === 'reversed') {
                return $run;
            }

            $journal = JournalEntry::query()->findOrFail($run->journal_entry_id);
            $period = $this->periodGuard->resolveOpenPeriodForPostingDateWithLock(now()->toDateString());

            $this->reversalService->reverse(
                entry: $journal,
                reversalPeriodId: $period->id,
                userId: $userId ?? 0
            );

            $run->update(['status' => 'reversed']);

            FixedAssetDepreciationSchedule::query()
                ->where('depreciation_run_id', $run->id)
                ->update([
                    'status' => 'reversed',
                ]);

            if ($userId) {
                $this->auditLogger->record(
                    $userId,
                    'fixed_asset_depreciation_run.reverse',
                    'fixed_asset_depreciation_run',
                    $run->id,
                    after: $run->toArray()
                );
            }

            return $run->fresh();
        });
    }
}
