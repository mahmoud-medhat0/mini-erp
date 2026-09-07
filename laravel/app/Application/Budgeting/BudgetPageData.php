<?php

namespace App\Application\Budgeting;

use App\Models\Account;
use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class BudgetPageData
{
    private const SORT_COLUMNS = [
        'code' => 'budget.code',
        'fiscal_year_year' => 'budget_fiscal_year.year',
        'version_code' => 'budget.version_code',
        'name' => 'budget.name',
        'status' => 'budget.status',
        'lines_count' => 'lines_count',
        'total_amount_minor' => 'total_amount_minor',
        'created_at' => 'budget.created_at',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
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

        return [
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

    /** @param array<string, mixed> $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = $this->filteredQuery($filters)
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
            ->withCount('lines')
            ->withSum('lines as total_amount_minor', 'amount_minor');

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $this->applySearch($builder, trim((string) request()->input('search.value', '')));
            })
            ->order(function (Builder $builder): void {
                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                    $data = $index === false ? null : request()->input("columns.$index.data");

                    if (! is_string($data) || ! isset(self::SORT_COLUMNS[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'asc' ? 'asc' : 'desc';
                    $builder->orderBy(self::SORT_COLUMNS[$data], $direction);
                }

                $builder->orderByDesc('budget.created_at')->orderBy('budget.id');
            })
            ->editColumn('name', fn (Budget $budget): array => $budget->getTranslations('name'))
            ->editColumn('lines_count', fn (Budget $budget): int => (int) $budget->lines_count)
            ->editColumn('total_amount_minor', fn (Budget $budget): int => (int) ($budget->total_amount_minor ?? 0))
            ->toJson();
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        $fiscalYearId = trim((string) ($filters['fiscal_year_id'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Budget::query()
            ->select(['budget.*', 'budget_fiscal_year.year as fiscal_year_year'])
            ->leftJoin('fiscal_year as budget_fiscal_year', 'budget_fiscal_year.id', '=', 'budget.fiscal_year_id')
            ->when($fiscalYearId !== '', fn (Builder $builder) => $builder->where('budget.fiscal_year_id', $fiscalYearId))
            ->when(
                $status !== '' && in_array($status, BudgetService::ALLOWED_STATUSES, true),
                fn (Builder $builder) => $builder->where('budget.status', $status),
            );

        $this->applySearch($query, $search);

        return $query;
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern = '%'.mb_strtolower($search).'%';
        $query->where(function (Builder $inner) use ($pattern): void {
            $inner->whereRaw('LOWER(budget.code) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(budget.version_code) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(CAST(budget.name AS TEXT)) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(budget.description, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(budget.status) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(budget.default_currency) LIKE ?', [$pattern])
                ->orWhereRaw('CAST(budget_fiscal_year.year AS TEXT) LIKE ?', [$pattern]);
        });
    }
}
