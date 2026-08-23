<?php

namespace App\Application\FixedAssets;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodGuard;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Domain\Audit\AuditLogger;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedAssetCapitalizationService
{
    public function __construct(
        private readonly AccountingAccountMappingService $mappingService,
        private readonly PostingEngine $postingEngine,
        private readonly ReversalService $reversalService,
        private readonly PeriodGuard $periodGuard,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function capitalize(string $assetId, string $mode, ?string $capitalizationDate = null, ?int $actorId = null): FixedAsset
    {
        if (! in_array($mode, ['opening_already_capitalized', 'manual_capitalization'], true)) {
            throw ValidationException::withMessages([
                'capitalization_mode' => ["Invalid capitalization mode [{$mode}]."],
            ]);
        }

        return DB::transaction(function () use ($assetId, $mode, $capitalizationDate, $actorId): FixedAsset {
            /** @var FixedAsset $asset */
            $asset = FixedAsset::query()->lockForUpdate()->findOrFail($assetId);

            if ($asset->capitalization_mode !== null) {
                if ($asset->status === 'active' && $asset->capitalization_mode === $mode) {
                    return $asset->load(['category', 'currencyModel', 'journalEntry', 'capitalizer']);
                }

                throw ValidationException::withMessages([
                    'asset' => ['Asset is already capitalized.'],
                ]);
            }

            if ($asset->status !== 'draft') {
                throw ValidationException::withMessages([
                    'asset' => ['Only draft assets can be capitalized.'],
                ]);
            }

            $before = $asset->toArray();
            $capDate = $capitalizationDate ?? ($asset->in_service_date ? $asset->in_service_date->format('Y-m-d') : now()->format('Y-m-d'));

            if ($mode === 'opening_already_capitalized') {
                $asset->status = 'active';
                $asset->capitalization_mode = 'opening_already_capitalized';
                $asset->capitalization_date = $capDate;
                $asset->journal_entry_id = null;
                $asset->capitalized_at = now();
                $asset->capitalized_by = $actorId;
                $asset->lock_version++;
                $asset->updated_by = $actorId;
                $asset->save();

                $this->auditLogger->record(
                    actorId: $actorId,
                    action: 'fixed_asset.capitalize_opening',
                    entityType: 'fixed_asset',
                    entityId: (string) $asset->id,
                    before: $before,
                    after: $asset->toArray(),
                );

                return $asset->load(['category', 'currencyModel', 'capitalizer']);
            }

            $period = $this->periodGuard->resolveOpenPeriodForPostingDateWithLock($capDate);
            $costAccount = $this->mappingService->getAccount('fixed_asset_cost');
            $clearingAccount = $this->mappingService->getAccount('fixed_asset_clearing');

            $journal = new JournalEntry([
                'id' => (string) Str::uuid(),
                'financial_period_id' => $period->id,
                'entry_date' => $capDate,
                'currency' => $asset->currency,
                'source_type' => 'fixed_asset_capitalization',
                'source_id' => $asset->id,
                'status' => 'approved',
                'description' => "fixed_asset.capitalization:{$asset->asset_number}",
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $journal->save();

            JournalLine::query()->create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journal->id,
                'line_no' => 1,
                'account_id' => $costAccount->id,
                'memo' => "fixed_asset.capitalization.cost:{$asset->asset_number}",
                'debit_minor' => $asset->cost_minor,
                'credit_minor' => 0,
                'currency' => $asset->currency,
                'fx_rate_e6' => 1000000,
                'debit_txn_minor' => $asset->cost_minor,
                'credit_txn_minor' => 0,
            ]);

            JournalLine::query()->create([
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journal->id,
                'line_no' => 2,
                'account_id' => $clearingAccount->id,
                'memo' => "fixed_asset.capitalization.clearing:{$asset->asset_number}",
                'debit_minor' => 0,
                'credit_minor' => $asset->cost_minor,
                'currency' => $asset->currency,
                'fx_rate_e6' => 1000000,
                'debit_txn_minor' => 0,
                'credit_txn_minor' => $asset->cost_minor,
            ]);

            $postedJournal = $this->postingEngine->post($journal, $actorId ?? 0, true);

            $asset->status = 'active';
            $asset->capitalization_mode = 'manual_capitalization';
            $asset->capitalization_date = $capDate;
            $asset->journal_entry_id = $postedJournal->id;
            $asset->capitalized_at = now();
            $asset->capitalized_by = $actorId;
            $asset->lock_version++;
            $asset->updated_by = $actorId;
            $asset->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'fixed_asset.capitalize_manual',
                entityType: 'fixed_asset',
                entityId: (string) $asset->id,
                before: $before,
                after: $asset->toArray(),
            );

            return $asset->load(['category', 'currencyModel', 'journalEntry', 'capitalizer']);
        });
    }

    public function reverseCapitalization(string $assetId, ?int $actorId = null): FixedAsset
    {
        return DB::transaction(function () use ($assetId, $actorId): FixedAsset {
            /** @var FixedAsset $asset */
            $asset = FixedAsset::query()->lockForUpdate()->findOrFail($assetId);

            if ($asset->status !== 'active' || $asset->capitalization_mode !== 'manual_capitalization' || ! $asset->journal_entry_id) {
                throw ValidationException::withMessages([
                    'asset' => ['Only manually capitalized active assets can be reversed.'],
                ]);
            }

            $before = $asset->toArray();

            $journal = JournalEntry::findOrFail($asset->journal_entry_id);
            $period = $this->periodGuard->resolveOpenPeriodForPostingDateWithLock(now()->toDateString());

            // Reverse journal entry
            $this->reversalService->reverse(
                entry: $journal,
                reversalPeriodId: $period->id,
                userId: $actorId ?? 0
            );

            // Revert asset to draft
            $asset->status = 'draft';
            $asset->capitalization_mode = null;
            $asset->capitalization_date = null;
            $asset->journal_entry_id = null;
            $asset->capitalized_at = null;
            $asset->capitalized_by = null;
            $asset->lock_version++;
            $asset->updated_by = $actorId;
            $asset->save();

            $this->auditLogger->record(
                actorId: $actorId,
                action: 'fixed_asset.reverse_capitalization',
                entityType: 'fixed_asset',
                entityId: (string) $asset->id,
                before: $before,
                after: $asset->toArray(),
            );

            return $asset->load(['category', 'currencyModel']);
        });
    }
}
