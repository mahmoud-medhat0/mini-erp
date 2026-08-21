<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GeneralLedgerService
{
    /**
     * Get General Journal stream with optional status and date filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function getGeneralJournal(array $filters = [])
    {
        $query = JournalEntry::query()
            ->with(['lines.account', 'period.fiscalYear', 'currencyRef', 'createdBy', 'postedBy'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['period_id'])) {
            $query->where('financial_period_id', $filters['period_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('entry_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('entry_date', '<=', $filters['end_date']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get General Ledger entries for a specific account or all accounts.
     * Derived STRICTLY from posted ledger entries.
     *
     * @param  array<string, mixed>  $filters
     * @return array{entries: LengthAwarePaginator, total_debit: int, total_credit: int, net_movement: int}
     */
    public function getGeneralLedger(array $filters = []): array
    {
        $query = LedgerEntry::query()
            ->with(['account', 'journalEntry', 'period.fiscalYear', 'currencyRef'])
            ->orderBy('entry_date', 'asc')
            ->orderBy('created_at', 'asc');

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['period_id'])) {
            $query->where('financial_period_id', $filters['period_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('entry_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('entry_date', '<=', $filters['end_date']);
        }

        $totalDebit = (int) (clone $query)->sum('debit_minor');
        $totalCredit = (int) (clone $query)->sum('credit_minor');
        $netMovement = $totalDebit - $totalCredit;

        $entries = $query->paginate($filters['per_page'] ?? 50);

        return [
            'entries' => $entries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'net_movement' => $netMovement,
        ];
    }

    /**
     * Compute Trial Balance derived STRICTLY from posted ledger entries for a period or date range.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, total_debit: int, total_credit: int, is_balanced: bool}
     */
    public function getTrialBalance(array $filters = []): array
    {
        $query = Account::query()
            ->with('group')
            ->where('is_active', true)
            ->orderBy('code', 'asc');

        $accounts = $query->get();
        $rows = [];
        $grandTotalDebit = 0;
        $grandTotalCredit = 0;

        foreach ($accounts as $account) {
            $ledgerQuery = LedgerEntry::query()->where('account_id', $account->id);

            if (! empty($filters['period_id'])) {
                $ledgerQuery->where('financial_period_id', $filters['period_id']);
            }

            if (! empty($filters['start_date'])) {
                $ledgerQuery->where('entry_date', '>=', $filters['start_date']);
            }

            if (! empty($filters['end_date'])) {
                $ledgerQuery->where('entry_date', '<=', $filters['end_date']);
            }

            $totalDebit = (int) (clone $ledgerQuery)->sum('debit_minor');
            $totalCredit = (int) (clone $ledgerQuery)->sum('credit_minor');

            if ($totalDebit === 0 && $totalCredit === 0 && empty($filters['include_zero'])) {
                continue;
            }

            $net = $totalDebit - $totalCredit;
            $debitBalance = $net > 0 ? $net : 0;
            $creditBalance = $net < 0 ? abs($net) : 0;

            $grandTotalDebit += $debitBalance;
            $grandTotalCredit += $creditBalance;

            $rows[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'type' => $account->type,
                'nature' => $account->nature,
                'group_name' => $account->group?->name,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'debit_balance' => $debitBalance,
                'credit_balance' => $creditBalance,
            ];
        }

        return [
            'rows' => $rows,
            'total_debit' => $grandTotalDebit,
            'total_credit' => $grandTotalCredit,
            'is_balanced' => $grandTotalDebit === $grandTotalCredit,
        ];
    }
}
