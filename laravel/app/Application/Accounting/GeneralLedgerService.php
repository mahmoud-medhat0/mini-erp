<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;
use Yajra\DataTables\Facades\DataTables;

class GeneralLedgerService
{
    private const TRIAL_BALANCE_SORT_COLUMNS = [
        'account_code' => 'trial_balance.account_code',
        'account_name' => 'trial_balance.account_name',
        'type' => 'trial_balance.type',
        'debit_balance' => 'trial_balance.debit_balance',
        'credit_balance' => 'trial_balance.credit_balance',
    ];

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
     * Aggregate-only payload for the Trial Balance page header. Rows are served
     * independently by {@see trialBalanceDataTable()} so the Inertia response
     * stays bounded when the chart of accounts grows.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total_debit: int, total_credit: int, is_balanced: bool, display_currency: string}
     */
    public function getTrialBalanceSummary(array $filters = []): array
    {
        $query = $this->trialBalanceQuery($filters);
        $totals = DB::query()
            ->fromSub($query, 'trial_balance_summary')
            ->selectRaw('COALESCE(SUM(debit_balance), 0) AS total_debit')
            ->selectRaw('COALESCE(SUM(credit_balance), 0) AS total_credit')
            ->first();

        $totalDebit = (int) ($totals->total_debit ?? 0);
        $totalCredit = (int) ($totals->total_credit ?? 0);

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => $totalDebit === $totalCredit,
            'display_currency' => $this->trialBalanceDisplayCurrencyFromQuery($filters),
        ];
    }

    /**
     * Server-side Trial Balance grid. The grouped ledger subquery is paginated
     * by Yajra/DataTables at SQL level; no account-sized row array enters the
     * Inertia payload.
     *
     * @param  array<string, mixed>  $filters
     */
    public function trialBalanceDataTable(array $filters = []): JsonResponse
    {
        $query = DB::query()
            ->fromSub($this->trialBalanceQuery($filters), 'trial_balance')
            ->select('trial_balance.*');

        return DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $pattern = "%{$search}%";
                $namePattern = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($namePattern, $pattern): void {
                    $nested->whereLike('trial_balance.account_code', $pattern)
                        ->orWhereRaw('LOWER(CAST(trial_balance.account_name AS TEXT)) LIKE ?', [$namePattern])
                        ->orWhereLike('trial_balance.type', $pattern);
                });
            })
            ->order(function (Builder $builder): void {
                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                    $data = $index === false ? null : request()->input("columns.$index.data");

                    if (! is_string($data) || ! isset(self::TRIAL_BALANCE_SORT_COLUMNS[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc';
                    $builder->orderBy(self::TRIAL_BALANCE_SORT_COLUMNS[$data], $direction);
                }

                $builder->orderBy('trial_balance.account_code')->orderBy('trial_balance.account_id');
            })
            ->editColumn('account_name', fn (stdClass $row): array|string => $this->decodeTranslations($row->account_name))
            ->editColumn('debit_balance', fn (stdClass $row): int => (int) $row->debit_balance)
            ->editColumn('credit_balance', fn (stdClass $row): int => (int) $row->credit_balance)
            ->toJson();
    }

    /** @param array<string, mixed> $filters */
    private function trialBalanceQuery(array $filters): Builder
    {
        $ledgerTotals = DB::table('ledger_entry')
            ->select('account_id')
            ->selectRaw('COALESCE(SUM(debit_minor), 0) AS total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) AS total_credit')
            ->when(! empty($filters['period_id']), fn (Builder $query) => $query->where('financial_period_id', $filters['period_id']))
            ->when(! empty($filters['branch_id']), fn (Builder $query) => $query->where('branch_id', $filters['branch_id']))
            ->when(! empty($filters['start_date']), fn (Builder $query) => $query->where('entry_date', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn (Builder $query) => $query->where('entry_date', '<=', $filters['end_date']))
            ->groupBy('account_id');

        $query = DB::table('account')
            ->leftJoinSub($ledgerTotals, 'ledger_totals', 'ledger_totals.account_id', '=', 'account.id')
            ->where('account.is_active', true)
            ->select([
                'account.id as account_id',
                'account.code as account_code',
                'account.name as account_name',
                'account.type',
                'account.nature',
                'account.currency as currency_code',
            ])
            ->selectRaw('COALESCE(ledger_totals.total_debit, 0) AS total_debit')
            ->selectRaw('COALESCE(ledger_totals.total_credit, 0) AS total_credit')
            ->selectRaw('CASE WHEN COALESCE(ledger_totals.total_debit, 0) > COALESCE(ledger_totals.total_credit, 0) THEN COALESCE(ledger_totals.total_debit, 0) - COALESCE(ledger_totals.total_credit, 0) ELSE 0 END AS debit_balance')
            ->selectRaw('CASE WHEN COALESCE(ledger_totals.total_credit, 0) > COALESCE(ledger_totals.total_debit, 0) THEN COALESCE(ledger_totals.total_credit, 0) - COALESCE(ledger_totals.total_debit, 0) ELSE 0 END AS credit_balance');

        if (! filter_var($filters['include_zero'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where(function (Builder $builder): void {
                $builder->whereRaw('COALESCE(ledger_totals.total_debit, 0) <> 0')
                    ->orWhereRaw('COALESCE(ledger_totals.total_credit, 0) <> 0');
            });
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function trialBalanceDisplayCurrencyFromQuery(array $filters): string
    {
        $currency = DB::query()
            ->fromSub($this->trialBalanceQuery($filters), 'trial_balance_currency')
            ->orderBy('trial_balance_currency.account_code')
            ->value('currency_code');

        return (string) (
            $currency
            ?: Company::query()->orderBy('created_at')->value('base_currency')
            ?: Account::query()->whereNotNull('currency')->orderBy('code')->value('currency')
            ?: config('erp_currencies.default')
        );
    }

    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
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
