<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\Company;
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
            ->with(['branch', 'lines.account', 'lines.branch', 'period.fiscalYear', 'currencyRef', 'createdBy', 'postedBy'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['period_id'])) {
            $query->where('financial_period_id', $filters['period_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where(function ($branchQuery) use ($filters): void {
                $branchQuery->where('branch_id', $filters['branch_id'])
                    ->orWhereHas('lines', fn ($lineQuery) => $lineQuery->where('branch_id', $filters['branch_id']));
            });
        }

        if (! empty($filters['start_date'])) {
            $query->where('entry_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('entry_date', '<=', $filters['end_date']);
        }

        return $query->paginate($filters['per_page'] ?? 20)->withQueryString();
    }

    /**
     * Get General Ledger summary totals derived STRICTLY from posted ledger entries.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total_debit: int, total_credit: int, net_movement: int}
     */
    public function getGeneralLedger(array $filters = []): array
    {
        return $this->getGeneralLedgerTotals($filters);
    }

    /**
     * Get General Ledger summary totals derived STRICTLY from posted ledger entries.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total_debit: int, total_credit: int, net_movement: int}
     */
    public function getGeneralLedgerTotals(array $filters = []): array
    {
        $query = LedgerEntry::query();

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['period_id'])) {
            $query->where('financial_period_id', $filters['period_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('entry_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('entry_date', '<=', $filters['end_date']);
        }

        $totalDebit = (int) (clone $query)->sum('debit_minor');
        $totalCredit = (int) (clone $query)->sum('credit_minor');

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'net_movement' => $totalDebit - $totalCredit,
        ];
    }

    /**
     * Compute Trial Balance derived STRICTLY from posted ledger entries for a period or date range.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, total_debit: int, total_credit: int, is_balanced: bool, display_currency: string}
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

        // Aggregate every account's movement in one grouped query. Summing per
        // account inside the loop issued two queries per account, so a 146-account
        // chart cost ~294 round trips to render a single trial balance.
        $totalsQuery = LedgerEntry::query()
            ->selectRaw('account_id')
            ->selectRaw('COALESCE(SUM(debit_minor), 0) AS total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) AS total_credit')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->groupBy('account_id');

        if (! empty($filters['period_id'])) {
            $totalsQuery->where('financial_period_id', $filters['period_id']);
        }

        if (! empty($filters['branch_id'])) {
            $totalsQuery->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['start_date'])) {
            $totalsQuery->where('entry_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $totalsQuery->where('entry_date', '<=', $filters['end_date']);
        }

        $totalsByAccount = $totalsQuery->get()->keyBy('account_id');

        foreach ($accounts as $account) {
            $accountTotals = $totalsByAccount->get($account->id);
            $totalDebit = (int) ($accountTotals->total_debit ?? 0);
            $totalCredit = (int) ($accountTotals->total_credit ?? 0);

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
                'currency_code' => $account->currency,
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
            'display_currency' => $this->trialBalanceDisplayCurrency($rows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function trialBalanceDisplayCurrency(array $rows): string
    {
        $firstRowCurrency = $rows[0]['currency_code'] ?? null;

        if ($firstRowCurrency) {
            return (string) $firstRowCurrency;
        }

        return (string) (
            Company::query()->orderBy('created_at')->value('base_currency')
            ?: Account::query()->whereNotNull('currency')->orderBy('code')->value('currency')
            ?: config('erp_currencies.default')
        );
    }
}
