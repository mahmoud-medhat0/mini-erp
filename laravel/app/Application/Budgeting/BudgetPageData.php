<?php

namespace App\Application\Budgeting;

use App\Models\Account;
use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class BudgetPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     budgets: LengthAwarePaginator,
     *     fiscalYears: EloquentCollection<int, FiscalYear>,
     *     financialPeriods: EloquentCollection<int, FinancialPeriod>,
     *     accounts: EloquentCollection<int, Account>,
     *     projects: EloquentCollection<int, Project>,
     *     costCenters: EloquentCollection<int, CostCenter>,
     *     currencies: EloquentCollection<int, Currency>,
     *     statuses: array<int, string>,
     *     filters: array{search: string, fiscal_year_id: string, status: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $fiscalYearId = trim((string) ($filters['fiscal_year_id'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $budgets = Budget::query()
            ->with([
                'fiscalYear',
                'submitter:id,name',
                'approver:id,name',
                'activator:id,name',
                'archiver:id,name',
                'canceller:id,name',
                'creator:id,name',
                'updater:id,name',
                'lines.financialPeriod.fiscalYear',
                'lines.account:id,code,name,type,nature,currency,is_active',
                'lines.project:id,code,name,status,is_active',
                'lines.costCenter:id,code,name,category,is_active',
                'lines.currencyRef:code,name,symbol',
            ])
            ->when($fiscalYearId !== '', fn ($query) => $query->where('fiscal_year_id', $fiscalYearId))
            ->when($status !== '' && in_array($status, BudgetService::ALLOWED_STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('version_code', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'budgets' => $budgets,
            'fiscalYears' => FiscalYear::query()->with('periods')->orderByDesc('year')->get(),
            'financialPeriods' => FinancialPeriod::query()->with('fiscalYear')->orderBy('month')->get(),
            'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type', 'nature', 'currency', 'is_active']),
            'projects' => Project::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'status', 'is_active']),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'category', 'is_active']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => BudgetService::ALLOWED_STATUSES,
            'filters' => [
                'search' => $search,
                'fiscal_year_id' => $fiscalYearId,
                'status' => $status,
            ],
        ];
    }
}
