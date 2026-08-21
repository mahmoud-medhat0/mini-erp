<?php

namespace App\Application\Accounting;

use App\Domain\Accounting\AccountingKernel;
use App\Domain\Audit\AuditLogger;
use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\OpeningBalance;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OpeningBalanceService
{
    public function __construct(
        private readonly PostingEngine $postingEngine,
        private readonly DatabaseIdempotencyStore $idempotencyStore,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Set or update opening balance draft rows for a fiscal year.
     *
     * @param  list<array{account_id: string, debit_minor: int, credit_minor: int}>  $balancesData
     */
    public function saveDraft(string $fiscalYearId, array $balancesData, int $userId): void
    {
        DB::transaction(function () use ($fiscalYearId, $balancesData, $userId): void {
            $fiscalYear = FiscalYear::findOrFail($fiscalYearId);

            foreach ($balancesData as $data) {
                $account = Account::findOrFail($data['account_id']);

                OpeningBalance::updateOrCreate(
                    [
                        'fiscal_year_id' => $fiscalYear->id,
                        'account_id' => $account->id,
                    ],
                    [
                        'debit_minor' => $data['debit_minor'],
                        'credit_minor' => $data['credit_minor'],
                        'currency' => $account->currency ?? 'EGP',
                        'fx_rate_e6' => 1000000,
                        'status' => 'draft',
                        'created_by' => $userId,
                    ]
                );
            }
        });
    }

    /**
     * Post opening balances for a fiscal year as an opening JournalEntry.
     */
    public function postOpeningBalances(string $fiscalYearId, int $userId): JournalEntry
    {
        $idempotencyKey = AccountingKernel::postingIdempotencyKey('opening_balance', $fiscalYearId, 'post');

        $result = $this->idempotencyStore->run(
            operation: 'accounting.opening_balance.post',
            rawKey: $idempotencyKey,
            callback: function () use ($fiscalYearId, $userId): JournalEntry {
                $postedJournal = DB::transaction(function () use ($fiscalYearId, $userId): JournalEntry {
                    $fiscalYear = FiscalYear::findOrFail($fiscalYearId);
                    $firstPeriod = FinancialPeriod::query()
                        ->where('fiscal_year_id', $fiscalYear->id)
                        ->orderBy('month', 'asc')
                        ->firstOrFail();

                    if (! $firstPeriod->isOpen()) {
                        throw new InvalidArgumentException(__('First financial period of fiscal year is closed.'));
                    }

                    $openingRows = OpeningBalance::query()
                        ->where('fiscal_year_id', $fiscalYear->id)
                        ->where('status', 'draft')
                        ->get();

                    if ($openingRows->isEmpty()) {
                        throw new InvalidArgumentException(__('No draft opening balances found for this fiscal year.'));
                    }

                    // 1. Create opening JournalEntry
                    $journal = JournalEntry::create([
                        'id' => (string) Str::uuid(),
                        'entry_date' => $fiscalYear->start_date,
                        'financial_period_id' => $firstPeriod->id,
                        'source_type' => 'opening_balance',
                        'source_id' => $fiscalYear->id,
                        'description' => __('Opening Balances for Fiscal Year :year', ['year' => $fiscalYear->year]),
                        'reference' => "OB-{$fiscalYear->year}",
                        'currency' => 'EGP',
                        'fx_rate_e6' => 1000000,
                        'status' => 'approved',
                        'created_by' => $userId,
                        'approved_by' => $userId,
                        'approved_at' => now(),
                    ]);

                    // 2. Create lines
                    $lineNo = 1;
                    foreach ($openingRows as $row) {
                        if ($row->debit_minor === 0 && $row->credit_minor === 0) {
                            continue;
                        }

                        JournalLine::create([
                            'id' => (string) Str::uuid(),
                            'journal_entry_id' => $journal->id,
                            'line_no' => $lineNo++,
                            'account_id' => $row->account_id,
                            'memo' => __('Opening balance :year', ['year' => $fiscalYear->year]),
                            'debit_minor' => $row->debit_minor,
                            'credit_minor' => $row->credit_minor,
                            'currency' => $row->currency,
                            'fx_rate_e6' => $row->fx_rate_e6,
                            'debit_txn_minor' => $row->debit_minor,
                            'credit_txn_minor' => $row->credit_minor,
                        ]);
                    }

                    // 3. Post journal
                    $posted = $this->postingEngine->post($journal, $userId, allowControlAccounts: true);

                    // 4. Mark opening rows posted
                    OpeningBalance::query()
                        ->where('fiscal_year_id', $fiscalYear->id)
                        ->where('status', 'draft')
                        ->update([
                            'status' => 'posted',
                            'journal_entry_id' => $posted->id,
                            'posted_by' => $userId,
                            'posted_at' => now(),
                        ]);

                    return $posted;
                });

                $this->auditLogger->record($userId, 'opening_balance.post', 'opening_balance', $fiscalYearId, after: [
                    'journal_entry_id' => $postedJournal->id,
                    'journal_number' => $postedJournal->number,
                ]);

                return $postedJournal;
            },
            actorId: $userId
        );

        return $result->value instanceof JournalEntry ? $result->value : JournalEntry::query()->where('source_type', 'opening_balance')->where('source_id', $fiscalYearId)->firstOrFail();
    }
}
