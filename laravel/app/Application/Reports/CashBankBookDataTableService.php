<?php

namespace App\Application\Reports;

use App\Models\BankAccount;
use App\Models\CashAccount;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\QueryDataTable;

class CashBankBookDataTableService
{
    /** @param array{account_id: string, date_from: string, date_to: string} $filters */
    public function cashBook(array $filters): JsonResponse
    {
        $account = CashAccount::query()->findOrFail($filters['account_id']);

        return $this->dataTable($this->bookRows(
            $account->gl_account_id,
            $account->currency,
            $filters['date_from'],
            $filters['date_to'],
        ), false)->toJson();
    }

    /** @param array{account_id: string, date_from: string, date_to: string} $filters */
    public function bankBook(array $filters): JsonResponse
    {
        $account = BankAccount::query()->findOrFail($filters['account_id']);

        return $this->dataTable($this->bookRows(
            $account->gl_account_id,
            $account->currency,
            $filters['date_from'],
            $filters['date_to'],
            true,
        ), true)->toJson();
    }

    /** @return array<string, mixed> */
    public function cashSummary(string $cashAccountId, string $dateFrom, string $dateTo): array
    {
        $account = CashAccount::query()->findOrFail($cashAccountId);

        return [
            'cash_account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'currency' => $account->currency,
                'gl_account_id' => $account->gl_account_id,
            ],
            ...$this->summary($account->gl_account_id, $account->currency, $dateFrom, $dateTo),
        ];
    }

    /** @return array<string, mixed> */
    public function bankSummary(string $bankAccountId, string $dateFrom, string $dateTo): array
    {
        $account = BankAccount::query()->findOrFail($bankAccountId);

        return [
            'bank_account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_number' => $account->account_number,
                'bank_name' => $account->bank_name,
                'currency' => $account->currency,
                'gl_account_id' => $account->gl_account_id,
            ],
            ...$this->summary($account->gl_account_id, $account->currency, $dateFrom, $dateTo),
        ];
    }

    private function dataTable(Builder $query, bool $includeReconciliation): QueryDataTable
    {
        $dataTable = DataTables::query($query)->filter(function (Builder $builder): void {
            $search = trim((string) request()->input('search.value', ''));

            if ($search === '') {
                return;
            }

            $like = '%'.mb_strtolower($search).'%';

            $builder->where(function (Builder $nested) use ($like): void {
                $nested
                    ->whereRaw("LOWER(COALESCE(book_rows.journal_number, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(book_rows.reference, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(book_rows.description, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(book_rows.source_type, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(book_rows.source_id, '')) LIKE ?", [$like]);
            });
        });

        foreach (['debit_minor', 'credit_minor', 'signed_movement_minor', 'balance_after_minor'] as $column) {
            $dataTable->editColumn($column, fn (stdClass $row): int => (int) $row->{$column});
        }

        if ($includeReconciliation) {
            $dataTable->editColumn('is_reconciled', fn (stdClass $row): bool => (bool) $row->is_reconciled);
        }

        return $dataTable;
    }

    private function bookRows(
        string $glAccountId,
        string $currency,
        string $dateFrom,
        string $dateTo,
        bool $includeReconciliation = false,
    ): Builder {
        $openingBalance = $this->openingBalance($glAccountId, $currency, $dateFrom);

        $rows = DB::table('ledger_entry as book_ledger')
            ->join('journal_entry as book_journal', 'book_journal.id', '=', 'book_ledger.journal_entry_id')
            ->leftJoin('journal_line as book_line', 'book_line.id', '=', 'book_ledger.journal_line_id')
            ->where('book_ledger.account_id', $glAccountId)
            ->where('book_ledger.currency', $currency)
            ->whereBetween('book_ledger.entry_date', [$dateFrom, $dateTo])
            ->select([
                'book_ledger.id as ledger_entry_id',
                'book_ledger.journal_entry_id',
                'book_ledger.entry_date',
                'book_ledger.created_at as entry_created_at',
                'book_ledger.debit_minor',
                'book_ledger.credit_minor',
                'book_journal.number as journal_number',
                'book_journal.source_type',
                'book_journal.source_id',
                'book_journal.reference',
            ])
            ->selectRaw("COALESCE(NULLIF(book_line.memo, ''), book_journal.description, '') AS description")
            ->selectRaw('(book_ledger.debit_minor - book_ledger.credit_minor) AS signed_movement_minor')
            ->selectRaw(
                '? + SUM(book_ledger.debit_minor - book_ledger.credit_minor) OVER (
                    ORDER BY book_ledger.entry_date ASC, book_ledger.created_at ASC, book_ledger.id ASC
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) AS balance_after_minor',
                [$openingBalance],
            );

        if ($includeReconciliation) {
            $rows->leftJoin('bank_reconciliation_line as book_reconciliation', function (JoinClause $join): void {
                $join->on('book_reconciliation.matched_ledger_entry_id', '=', 'book_ledger.id')
                    ->where('book_reconciliation.status', '=', 'matched');
            })->addSelect([
                'book_reconciliation.id as reconciliation_line_id',
                'book_reconciliation.bank_reconciliation_id as reconciliation_id',
            ])->selectRaw('CASE WHEN book_reconciliation.id IS NULL THEN 0 ELSE 1 END AS is_reconciled');
        }

        return DB::query()->fromSub($rows, 'book_rows')->select('book_rows.*');
    }

    /** @return array<string, int|string> */
    private function summary(string $glAccountId, string $currency, string $dateFrom, string $dateTo): array
    {
        $openingBalance = $this->openingBalance($glAccountId, $currency, $dateFrom);
        $period = DB::table('ledger_entry')
            ->where('account_id', $glAccountId)
            ->where('currency', $currency)
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(SUM(debit_minor), 0) AS debit_minor')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) AS credit_minor')
            ->first();
        $periodDebit = (int) ($period->debit_minor ?? 0);
        $periodCredit = (int) ($period->credit_minor ?? 0);
        $periodMovement = $periodDebit - $periodCredit;

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'currency' => $currency,
            'opening_balance_minor' => $openingBalance,
            'period_debit_minor' => $periodDebit,
            'period_credit_minor' => $periodCredit,
            'period_movement_minor' => $periodMovement,
            'closing_balance_minor' => $openingBalance + $periodMovement,
        ];
    }

    private function openingBalance(string $glAccountId, string $currency, string $dateFrom): int
    {
        return (int) DB::table('ledger_entry')
            ->where('account_id', $glAccountId)
            ->where('currency', $currency)
            ->where('entry_date', '<', $dateFrom)
            ->sum(DB::raw('debit_minor - credit_minor'));
    }
}
