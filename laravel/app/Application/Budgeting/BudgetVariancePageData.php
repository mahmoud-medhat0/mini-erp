<?php

namespace App\Application\Budgeting;

use App\Models\Account;
use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class BudgetVariancePageData
{
    /**
     * @return array{
     *     budgets: EloquentCollection<int, Budget>,
     *     fiscalYears: EloquentCollection<int, FiscalYear>,
     *     financialPeriods: EloquentCollection<int, FinancialPeriod>,
     *     accounts: EloquentCollection<int, Account>,
     *     projects: EloquentCollection<int, Project>,
     *     costCenters: EloquentCollection<int, CostCenter>,
     *     currencies: EloquentCollection<int, Currency>
     * }
     */
    public function options(): array
    {
        return [
            'budgets' => Budget::query()
                ->whereIn('status', ['active', 'approved'])
                ->with('fiscalYear')
                ->orderByDesc('created_at')
                ->get(['id', 'fiscal_year_id', 'code', 'version_code', 'name', 'status', 'default_currency']),
            'fiscalYears' => FiscalYear::query()
                ->with('periods')
                ->orderByDesc('year')
                ->get(['id', 'year', 'start_date', 'end_date', 'status']),
            'financialPeriods' => FinancialPeriod::query()
                ->with('fiscalYear')
                ->orderBy('month')
                ->get(['id', 'fiscal_year_id', 'month', 'start_date', 'end_date', 'status']),
            'accounts' => Account::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'nature', 'currency', 'is_active']),
            'projects' => Project::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'status', 'is_active']),
            'costCenters' => CostCenter::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'category', 'is_active']),
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get(['code', 'name', 'symbol']),
        ];
    }
}
