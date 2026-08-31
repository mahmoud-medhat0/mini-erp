<?php

namespace App\Application\Accounting;

use App\Application\Support\CurrencyInput;
use App\Domain\Accounting\AccountingKernel;
use App\Domain\Accounting\DraftEntry;
use App\Domain\Accounting\DraftLine;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JournalDraftService
{
    public static function assertDateInPeriod(FinancialPeriod $period, string $entryDate): void
    {
        $date = Carbon::parse($entryDate)->toDateString();
        $startDate = Carbon::parse($period->start_date)->toDateString();
        $endDate = Carbon::parse($period->end_date)->toDateString();

        if ($date < $startDate || $date > $endDate) {
            throw new InvalidArgumentException(__(
                'Journal entry date :date is outside target financial period (:start to :end).',
                ['date' => $date, 'start' => $startDate, 'end' => $endDate]
            ));
        }
    }

    /**
     * Create a new draft journal entry with lines.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{account_id: string, debit_minor: int, credit_minor: int, memo?: string|null, branch_id?: string|null, project_id?: string|null, cost_center_id?: string|null}>  $linesData
     */
    public function createDraft(array $data, array $linesData, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($data, $linesData, $userId): JournalEntry {
            $period = FinancialPeriod::findOrFail($data['financial_period_id']);
            if (! $period->isOpen()) {
                throw new InvalidArgumentException(__('Target financial period is closed or locked.'));
            }

            self::assertDateInPeriod($period, (string) $data['entry_date']);

            $entry = JournalEntry::create([
                'id' => (string) Str::uuid(),
                'entry_date' => $data['entry_date'],
                'financial_period_id' => $period->id,
                'branch_id' => $data['branch_id'] ?? null,
                'source_type' => $data['source_type'] ?? 'manual_journal',
                'source_id' => $data['source_id'] ?? null,
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                'currency' => CurrencyInput::required($data['currency'] ?? null),
                'fx_rate_e6' => $data['fx_rate_e6'] ?? 1000000,
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $draftLines = [];
            foreach ($linesData as $index => $lineData) {
                $account = Account::findOrFail($lineData['account_id']);
                if (! $account->is_active) {
                    throw new InvalidArgumentException(__('Account :code is inactive.', ['code' => $account->code]));
                }

                $projectId = ! empty($lineData['project_id']) ? $lineData['project_id'] : null;
                if ($projectId !== null) {
                    $project = Project::find($projectId);
                    if (! $project || ! $project->is_active) {
                        throw new InvalidArgumentException(__('Selected project is inactive or missing.'));
                    }
                }

                $costCenterId = ! empty($lineData['cost_center_id']) ? $lineData['cost_center_id'] : null;
                if ($costCenterId !== null) {
                    $costCenter = CostCenter::find($costCenterId);
                    if (! $costCenter || ! $costCenter->is_active) {
                        throw new InvalidArgumentException(__('Selected cost center is inactive or missing.'));
                    }
                }

                JournalLine::create([
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $entry->id,
                    'line_no' => $index + 1,
                    'account_id' => $account->id,
                    'branch_id' => $lineData['branch_id'] ?? $entry->branch_id,
                    'project_id' => $projectId,
                    'cost_center_id' => $costCenterId,
                    'memo' => $lineData['memo'] ?? null,
                    'debit_minor' => $lineData['debit_minor'],
                    'credit_minor' => $lineData['credit_minor'],
                    'currency' => $entry->currency,
                    'fx_rate_e6' => $entry->fx_rate_e6,
                    'debit_txn_minor' => $lineData['debit_minor'],
                    'credit_txn_minor' => $lineData['credit_minor'],
                ]);

                $draftLines[] = new DraftLine(
                    accountId: $account->id,
                    debitMinor: (int) $lineData['debit_minor'],
                    creditMinor: (int) $lineData['credit_minor'],
                    memo: $lineData['memo'] ?? null,
                    projectId: $projectId,
                    costCenterId: $costCenterId,
                );
            }

            $kernelEntry = new DraftEntry(
                sourceType: $entry->source_type ?? 'manual_journal',
                sourceId: (string) $entry->id,
                date: Carbon::parse($entry->entry_date),
                currency: $entry->currency,
                fxRate: (int) $entry->fx_rate_e6,
                lines: $draftLines,
                description: $entry->description
            );

            AccountingKernel::assertBalanced($kernelEntry);

            return $entry->fresh(['branch', 'lines.account', 'lines.branch', 'lines.project', 'lines.costCenter']);
        });
    }

    /**
     * Update an existing draft journal entry.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{account_id: string, debit_minor: int, credit_minor: int, memo?: string|null, branch_id?: string|null, project_id?: string|null, cost_center_id?: string|null}>  $linesData
     */
    public function updateDraft(JournalEntry $entry, array $data, array $linesData, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data, $linesData, $userId): JournalEntry {
            if ($entry->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft entries can be edited.'));
            }

            if (isset($data['lock_version']) && (int) $entry->lock_version !== (int) $data['lock_version']) {
                throw new InvalidArgumentException(__('Stale journal draft update detected.'));
            }

            $period = FinancialPeriod::findOrFail($data['financial_period_id'] ?? $entry->financial_period_id);
            if (! $period->isOpen()) {
                throw new InvalidArgumentException(__('Target financial period is closed or locked.'));
            }

            $entryDate = (string) ($data['entry_date'] ?? $entry->entry_date);
            self::assertDateInPeriod($period, $entryDate);

            $entry->update([
                'entry_date' => $entryDate,
                'financial_period_id' => $period->id,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $entry->branch_id,
                'description' => $data['description'] ?? $entry->description,
                'reference' => $data['reference'] ?? $entry->reference,
                'currency' => $data['currency'] ?? $entry->currency,
                'fx_rate_e6' => $data['fx_rate_e6'] ?? $entry->fx_rate_e6,
                'updated_by' => $userId,
                'lock_version' => $entry->lock_version + 1,
            ]);

            $entry->lines()->delete();

            $draftLines = [];
            foreach ($linesData as $index => $lineData) {
                $account = Account::findOrFail($lineData['account_id']);
                if (! $account->is_active) {
                    throw new InvalidArgumentException(__('Account :code is inactive.', ['code' => $account->code]));
                }

                $projectId = ! empty($lineData['project_id']) ? $lineData['project_id'] : null;
                if ($projectId !== null) {
                    $project = Project::find($projectId);
                    if (! $project || ! $project->is_active) {
                        throw new InvalidArgumentException(__('Selected project is inactive or missing.'));
                    }
                }

                $costCenterId = ! empty($lineData['cost_center_id']) ? $lineData['cost_center_id'] : null;
                if ($costCenterId !== null) {
                    $costCenter = CostCenter::find($costCenterId);
                    if (! $costCenter || ! $costCenter->is_active) {
                        throw new InvalidArgumentException(__('Selected cost center is inactive or missing.'));
                    }
                }

                JournalLine::create([
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $entry->id,
                    'line_no' => $index + 1,
                    'account_id' => $account->id,
                    'branch_id' => $lineData['branch_id'] ?? $entry->branch_id,
                    'project_id' => $projectId,
                    'cost_center_id' => $costCenterId,
                    'memo' => $lineData['memo'] ?? null,
                    'debit_minor' => $lineData['debit_minor'],
                    'credit_minor' => $lineData['credit_minor'],
                    'currency' => $entry->currency,
                    'fx_rate_e6' => $entry->fx_rate_e6,
                    'debit_txn_minor' => $lineData['debit_minor'],
                    'credit_txn_minor' => $lineData['credit_minor'],
                ]);

                $draftLines[] = new DraftLine(
                    accountId: $account->id,
                    debitMinor: (int) $lineData['debit_minor'],
                    creditMinor: (int) $lineData['credit_minor'],
                    memo: $lineData['memo'] ?? null,
                    projectId: $projectId,
                    costCenterId: $costCenterId,
                );
            }

            $kernelEntry = new DraftEntry(
                sourceType: $entry->source_type ?? 'manual_journal',
                sourceId: (string) $entry->id,
                date: Carbon::parse($entry->entry_date),
                currency: $entry->currency,
                fxRate: (int) $entry->fx_rate_e6,
                lines: $draftLines,
                description: $entry->description
            );

            AccountingKernel::assertBalanced($kernelEntry);

            return $entry->fresh(['branch', 'lines.account', 'lines.branch', 'lines.project', 'lines.costCenter']);
        });
    }

    public function submit(JournalEntry $entry, int $userId): JournalEntry
    {
        if (! in_array($entry->status, ['draft', 'submitted'], true)) {
            throw new InvalidArgumentException(__('Only draft entries can be submitted.'));
        }

        $entry->update([
            'status' => 'submitted',
            'submitted_by' => $userId,
            'submitted_at' => now(),
            'updated_by' => $userId,
        ]);

        return $entry;
    }

    public function approve(JournalEntry $entry, int $userId): JournalEntry
    {
        if (! in_array($entry->status, ['draft', 'submitted', 'approved'], true)) {
            throw new InvalidArgumentException(__('Entry cannot be approved in status: :status', ['status' => $entry->status]));
        }

        $entry->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'updated_by' => $userId,
        ]);

        return $entry;
    }
}
