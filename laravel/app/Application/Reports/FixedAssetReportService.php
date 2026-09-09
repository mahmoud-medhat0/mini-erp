<?php

namespace App\Application\Reports;

use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\FixedAssetDisposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Facades\DataTables;

class FixedAssetReportService
{
    /** @var array<string, string> */
    private const ASSET_SORT_COLUMNS = [
        'asset_number' => 'fixed_asset.asset_number',
        'cost_minor' => 'fixed_asset.cost_minor',
        'opening_accumulated_depreciation_minor' => 'fixed_asset.opening_accumulated_depreciation_minor',
        'posted_accumulated_depreciation_minor' => 'posted_accumulated_depreciation_minor',
        'total_accumulated_depreciation_minor' => 'total_accumulated_depreciation_minor',
        'net_book_value_minor' => 'net_book_value_minor',
        'status' => 'fixed_asset.status',
    ];

    /** @var array<string, string> */
    private const SCHEDULE_SORT_COLUMNS = [
        'period_number' => 'fixed_asset_depreciation_schedule.period_number',
        'period_start_date' => 'fixed_asset_depreciation_schedule.period_start_date',
        'period_end_date' => 'fixed_asset_depreciation_schedule.period_end_date',
        'depreciation_minor' => 'fixed_asset_depreciation_schedule.depreciation_minor',
        'accumulated_depreciation_minor' => 'fixed_asset_depreciation_schedule.accumulated_depreciation_minor',
        'net_book_value_minor' => 'fixed_asset_depreciation_schedule.net_book_value_minor',
        'status' => 'fixed_asset_depreciation_schedule.status',
    ];

    /** @var array<string, string> */
    private const RUN_SORT_COLUMNS = [
        'number' => 'fixed_asset_depreciation_run.number',
        'run_date' => 'fixed_asset_depreciation_run.run_date',
        'asset_count' => 'fixed_asset_depreciation_run.asset_count',
        'total_depreciation_minor' => 'fixed_asset_depreciation_run.total_depreciation_minor',
        'status' => 'fixed_asset_depreciation_run.status',
    ];

    /** @var array<string, string> */
    private const DISPOSAL_SORT_COLUMNS = [
        'number' => 'fixed_asset_disposal.number',
        'disposal_date' => 'fixed_asset_disposal.disposal_date',
        'disposal_type' => 'fixed_asset_disposal.disposal_type',
        'proceeds_minor' => 'fixed_asset_disposal.proceeds_minor',
        'net_book_value_minor' => 'fixed_asset_disposal.net_book_value_minor',
        'gain_loss_minor' => 'gain_loss_minor',
        'status' => 'fixed_asset_disposal.status',
    ];

    public function register(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $assets = $this->registerQuery($filters)->paginate($perPage)->withQueryString();

        $assets->setCollection($this->assetRows($assets->getCollection()));

        return $assets;
    }

    public function allRegisterRows(array $filters = []): Collection
    {
        return $this->assetRows($this->registerQuery($filters)->get());
    }

    public function netBookValues(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $assets = $this->registerQuery($filters)
            ->orderBy('asset_number')
            ->paginate($perPage)
            ->withQueryString();

        $assets->setCollection($this->assetRows($assets->getCollection()));

        return $assets;
    }

    public function allNetBookValueRows(array $filters = []): Collection
    {
        return $this->assetRows(
            $this->registerQuery($filters)
                ->orderBy('asset_number')
                ->get()
        );
    }

    public function depreciationSchedule(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $schedules = $this->depreciationScheduleQuery($filters)->paginate($perPage)->withQueryString();

        $schedules->setCollection($schedules->getCollection()->map(
            fn (FixedAssetDepreciationSchedule $schedule): array => $this->depreciationScheduleRow($schedule)
        )->values());

        return $schedules;
    }

    public function allDepreciationScheduleRows(array $filters = []): Collection
    {
        return $this->depreciationScheduleQuery($filters)
            ->get()
            ->map(fn (FixedAssetDepreciationSchedule $schedule): array => $this->depreciationScheduleRow($schedule))
            ->values();
    }

    public function depreciationRuns(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $runs = $this->depreciationRunQuery($filters)->paginate($perPage)->withQueryString();

        $runs->setCollection($runs->getCollection()->map(
            fn (FixedAssetDepreciationRun $run): array => $this->depreciationRunRow($run)
        )->values());

        return $runs;
    }

    public function allDepreciationRunRows(array $filters = []): Collection
    {
        return $this->depreciationRunQuery($filters)
            ->get()
            ->map(fn (FixedAssetDepreciationRun $run): array => $this->depreciationRunRow($run))
            ->values();
    }

    public function disposals(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $disposals = $this->disposalQuery($filters)->paginate($perPage)->withQueryString();

        $disposals->setCollection($disposals->getCollection()->map(
            fn (FixedAssetDisposal $disposal): array => $this->disposalRow($disposal)
        )->values());

        return $disposals;
    }

    public function allDisposalRows(array $filters = []): Collection
    {
        return $this->disposalQuery($filters)
            ->get()
            ->map(fn (FixedAssetDisposal $disposal): array => $this->disposalRow($disposal))
            ->values();
    }

    /** @param array<string, mixed> $filters */
    public function registerDataTable(array $filters = []): JsonResponse
    {
        return $this->assetDataTable($filters)->toJson();
    }

    /** @param array<string, mixed> $filters */
    public function netBookValueDataTable(array $filters = []): JsonResponse
    {
        return $this->assetDataTable($filters)->toJson();
    }

    /** @param array<string, mixed> $filters */
    public function depreciationScheduleDataTable(array $filters = []): JsonResponse
    {
        $query = FixedAssetDepreciationSchedule::query()
            ->with(['asset.category', 'financialPeriod.fiscalYear', 'depreciationRun', 'journalEntry'])
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status));

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchDepreciationSchedules($builder, $search),
            self::SCHEDULE_SORT_COLUMNS,
            [
                ['fixed_asset_depreciation_schedule.period_start_date', 'asc'],
                ['fixed_asset_depreciation_schedule.period_number', 'asc'],
                ['fixed_asset_depreciation_schedule.fixed_asset_id', 'asc'],
            ],
        )
            ->editColumn('period_number', fn (FixedAssetDepreciationSchedule $schedule): int => (int) $schedule->period_number)
            ->editColumn('period_start_date', fn (FixedAssetDepreciationSchedule $schedule): ?string => $this->dateString($schedule->period_start_date))
            ->editColumn('period_end_date', fn (FixedAssetDepreciationSchedule $schedule): ?string => $this->dateString($schedule->period_end_date))
            ->editColumn('depreciation_minor', fn (FixedAssetDepreciationSchedule $schedule): int => (int) $schedule->depreciation_minor)
            ->editColumn('accumulated_depreciation_minor', fn (FixedAssetDepreciationSchedule $schedule): int => (int) $schedule->accumulated_depreciation_minor)
            ->editColumn('net_book_value_minor', fn (FixedAssetDepreciationSchedule $schedule): int => (int) $schedule->net_book_value_minor)
            ->addColumn('asset', fn (FixedAssetDepreciationSchedule $schedule): ?array => $this->assetReference($schedule->asset))
            ->addColumn('financial_period', fn (FixedAssetDepreciationSchedule $schedule): ?array => $this->financialPeriodReference($schedule->financialPeriod))
            ->addColumn('depreciation_run_number', fn (FixedAssetDepreciationSchedule $schedule): ?string => $schedule->depreciationRun?->number)
            ->addColumn('journal_number', fn (FixedAssetDepreciationSchedule $schedule): ?string => $schedule->journalEntry?->number)
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the {en, ar} translation maps nested under `asset`.
            ->rawColumns(['asset.name', 'asset.name.en', 'asset.name.ar', 'asset.category.name', 'asset.category.name.en', 'asset.category.name.ar'])
            ->toJson();
    }

    /** @param array<string, mixed> $filters */
    public function depreciationRunDataTable(array $filters = []): JsonResponse
    {
        $query = FixedAssetDepreciationRun::query()
            ->with(['financialPeriod.fiscalYear', 'journalEntry'])
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['period_id'] ?? null, fn (Builder $builder, string $periodId) => $builder->where('financial_period_id', $periodId));

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchDepreciationRuns($builder, $search),
            self::RUN_SORT_COLUMNS,
            [
                ['fixed_asset_depreciation_run.run_date', 'desc'],
                ['fixed_asset_depreciation_run.number', 'desc'],
            ],
        )
            ->editColumn('run_date', fn (FixedAssetDepreciationRun $run): ?string => $this->dateString($run->run_date))
            ->editColumn('total_depreciation_minor', fn (FixedAssetDepreciationRun $run): int => (int) $run->total_depreciation_minor)
            ->editColumn('asset_count', fn (FixedAssetDepreciationRun $run): int => (int) $run->asset_count)
            ->addColumn('financial_period', fn (FixedAssetDepreciationRun $run): ?array => $this->financialPeriodReference($run->financialPeriod))
            ->addColumn('journal_number', fn (FixedAssetDepreciationRun $run): ?string => $run->journalEntry?->number)
            ->toJson();
    }

    /** @param array<string, mixed> $filters */
    public function disposalDataTable(array $filters = []): JsonResponse
    {
        $query = FixedAssetDisposal::query()
            ->with(['asset.category', 'financialPeriod.fiscalYear', 'journalEntry', 'reversalJournalEntry'])
            ->select('fixed_asset_disposal.*')
            ->selectRaw('(fixed_asset_disposal.gain_minor - fixed_asset_disposal.loss_minor) as gain_loss_minor')
            ->when($filters['disposal_type'] ?? null, fn (Builder $builder, string $type) => $builder->where('disposal_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status));

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchDisposals($builder, $search),
            self::DISPOSAL_SORT_COLUMNS,
            [
                ['fixed_asset_disposal.disposal_date', 'desc'],
                ['fixed_asset_disposal.number', 'desc'],
            ],
        )
            ->editColumn('disposal_date', fn (FixedAssetDisposal $disposal): ?string => $this->dateString($disposal->disposal_date))
            ->editColumn('proceeds_minor', fn (FixedAssetDisposal $disposal): int => (int) $disposal->proceeds_minor)
            ->editColumn('net_book_value_minor', fn (FixedAssetDisposal $disposal): int => (int) $disposal->net_book_value_minor)
            ->editColumn('gain_minor', fn (FixedAssetDisposal $disposal): int => (int) $disposal->gain_minor)
            ->editColumn('loss_minor', fn (FixedAssetDisposal $disposal): int => (int) $disposal->loss_minor)
            ->editColumn('gain_loss_minor', fn (FixedAssetDisposal $disposal): int => (int) $disposal->gain_loss_minor)
            ->addColumn('asset', fn (FixedAssetDisposal $disposal): ?array => $this->assetReference($disposal->asset))
            ->addColumn('financial_period', fn (FixedAssetDisposal $disposal): ?array => $this->financialPeriodReference($disposal->financialPeriod))
            ->addColumn('journal_number', fn (FixedAssetDisposal $disposal): ?string => $disposal->journalEntry?->number)
            ->addColumn('reversal_journal_number', fn (FixedAssetDisposal $disposal): ?string => $disposal->reversalJournalEntry?->number)
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the {en, ar} translation maps nested under `asset`.
            ->rawColumns(['asset.name', 'asset.name.en', 'asset.name.ar', 'asset.category.name', 'asset.category.name.en', 'asset.category.name.ar'])
            ->toJson();
    }

    /** @param array<string, mixed> $filters */
    private function assetDataTable(array $filters): EloquentDataTable
    {
        $postedDepreciation = FixedAssetDepreciationSchedule::query()
            ->select('fixed_asset_id')
            ->selectRaw('COALESCE(SUM(depreciation_minor), 0) as posted_minor')
            ->where('status', 'posted')
            ->groupBy('fixed_asset_id');

        $query = FixedAsset::query()
            ->with('category')
            ->leftJoinSub($postedDepreciation, 'posted_depreciation', 'posted_depreciation.fixed_asset_id', '=', 'fixed_asset.id')
            ->select('fixed_asset.*')
            ->selectRaw('COALESCE(posted_depreciation.posted_minor, 0) as posted_accumulated_depreciation_minor')
            ->selectRaw('(fixed_asset.opening_accumulated_depreciation_minor + COALESCE(posted_depreciation.posted_minor, 0)) as total_accumulated_depreciation_minor')
            ->selectRaw('CASE WHEN fixed_asset.cost_minor - fixed_asset.opening_accumulated_depreciation_minor - COALESCE(posted_depreciation.posted_minor, 0) > 0 THEN fixed_asset.cost_minor - fixed_asset.opening_accumulated_depreciation_minor - COALESCE(posted_depreciation.posted_minor, 0) ELSE 0 END as net_book_value_minor')
            ->when($filters['category_id'] ?? null, fn (Builder $builder, string $categoryId) => $builder->where('fixed_asset.fixed_asset_category_id', $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('fixed_asset.status', $status));

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchAssets($builder, $search),
            self::ASSET_SORT_COLUMNS,
            [['fixed_asset.asset_number', 'asc']],
        )
            ->editColumn('name', fn (FixedAsset $asset): array|string|null => $this->translations($asset, 'name'))
            ->addColumn('category', fn (FixedAsset $asset): ?array => $asset->category ? [
                'id' => $asset->category->id,
                'code' => $asset->category->code,
                'name' => $this->translations($asset->category, 'name'),
            ] : null)
            ->editColumn('acquisition_date', fn (FixedAsset $asset): ?string => $this->dateString($asset->acquisition_date))
            ->editColumn('in_service_date', fn (FixedAsset $asset): ?string => $this->dateString($asset->in_service_date))
            ->editColumn('useful_life_months', fn (FixedAsset $asset): int => (int) $asset->useful_life_months)
            ->editColumn('cost_minor', fn (FixedAsset $asset): int => (int) $asset->cost_minor)
            ->editColumn('salvage_value_minor', fn (FixedAsset $asset): int => (int) $asset->salvage_value_minor)
            ->editColumn('opening_accumulated_depreciation_minor', fn (FixedAsset $asset): int => (int) $asset->opening_accumulated_depreciation_minor)
            ->editColumn('posted_accumulated_depreciation_minor', fn (FixedAsset $asset): int => (int) $asset->posted_accumulated_depreciation_minor)
            ->editColumn('total_accumulated_depreciation_minor', fn (FixedAsset $asset): int => (int) $asset->total_accumulated_depreciation_minor)
            ->editColumn('net_book_value_minor', fn (FixedAsset $asset): int => (int) $asset->net_book_value_minor)
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the {en, ar} translation maps.
            ->rawColumns(['name', 'name.en', 'name.ar', 'category.name', 'category.name.en', 'category.name.ar']);
    }

    /**
     * @param  array<string, string>  $sortColumns
     * @param  list<array{0: string, 1: 'asc'|'desc'}>  $defaultOrder
     */
    private function dataTable(Builder $query, callable $search, array $sortColumns, array $defaultOrder): EloquentDataTable
    {
        return DataTables::eloquent($query)
            ->filter(function (Builder $builder) use ($search): void {
                $keyword = trim((string) request()->input('search.value', ''));

                if ($keyword !== '') {
                    $search($builder, $keyword);
                }
            })
            ->order(function (Builder $builder) use ($sortColumns, $defaultOrder): void {
                $ordered = false;

                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                    $data = $index === false ? null : request()->input("columns.$index.data");

                    if (! is_string($data) || ! isset($sortColumns[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc';
                    $builder->orderBy($sortColumns[$data], $direction);
                    $ordered = true;
                }

                if (! $ordered) {
                    foreach ($defaultOrder as [$column, $direction]) {
                        $builder->orderBy($column, $direction);
                    }
                }

                $builder->orderBy($builder->qualifyColumn('id'));
            });
    }

    private function searchAssets(Builder $query, string $search): void
    {
        $pattern = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $nested) use ($search, $pattern): void {
            $nested->whereLike('fixed_asset.asset_number', "%{$search}%")
                ->orWhereLike('fixed_asset.serial_number', "%{$search}%")
                ->orWhereRaw('LOWER(CAST(fixed_asset.name AS TEXT)) LIKE ?', [$pattern])
                ->orWhereHas('category', fn (Builder $category) => $category
                    ->whereLike('code', "%{$search}%")
                    ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [$pattern]));
        });
    }

    private function searchDepreciationSchedules(Builder $query, string $search): void
    {
        $pattern = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $nested) use ($search, $pattern): void {
            $nested->whereRaw('CAST(fixed_asset_depreciation_schedule.period_start_date AS TEXT) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('CAST(fixed_asset_depreciation_schedule.period_end_date AS TEXT) LIKE ?', ["%{$search}%"])
                ->orWhereLike('fixed_asset_depreciation_schedule.status', "%{$search}%")
                ->orWhereHas('asset', fn (Builder $asset) => $asset
                    ->whereLike('asset_number', "%{$search}%")
                    ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [$pattern]));
        });
    }

    private function searchDepreciationRuns(Builder $query, string $search): void
    {
        $query->where(function (Builder $nested) use ($search): void {
            $nested->whereLike('fixed_asset_depreciation_run.number', "%{$search}%")
                ->orWhereRaw('CAST(fixed_asset_depreciation_run.run_date AS TEXT) LIKE ?', ["%{$search}%"])
                ->orWhereLike('fixed_asset_depreciation_run.status', "%{$search}%")
                ->orWhereHas('journalEntry', fn (Builder $journal) => $journal->whereLike('number', "%{$search}%"));
        });
    }

    private function searchDisposals(Builder $query, string $search): void
    {
        $pattern = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $nested) use ($search, $pattern): void {
            $nested->whereLike('fixed_asset_disposal.number', "%{$search}%")
                ->orWhereRaw('CAST(fixed_asset_disposal.disposal_date AS TEXT) LIKE ?', ["%{$search}%"])
                ->orWhereLike('fixed_asset_disposal.disposal_type', "%{$search}%")
                ->orWhereLike('fixed_asset_disposal.status', "%{$search}%")
                ->orWhereHas('asset', fn (Builder $asset) => $asset
                    ->whereLike('asset_number', "%{$search}%")
                    ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [$pattern]));
        });
    }

    private function assetReference(?FixedAsset $asset): ?array
    {
        if (! $asset) {
            return null;
        }

        return [
            'id' => $asset->id,
            'asset_number' => $asset->asset_number,
            'name' => $this->translations($asset, 'name'),
            'currency' => $asset->currency,
            'category' => $asset->category ? [
                'id' => $asset->category->id,
                'code' => $asset->category->code,
                'name' => $this->translations($asset->category, 'name'),
            ] : null,
        ];
    }

    private function financialPeriodReference(?object $period): ?array
    {
        if (! $period) {
            return null;
        }

        return [
            'id' => $period->id,
            'year' => $period->fiscalYear?->year,
            'month' => (int) $period->month,
            'start_date' => $this->dateString($period->start_date),
            'end_date' => $this->dateString($period->end_date),
            'status' => $period->status,
        ];
    }

    private function registerQuery(array $filters): Builder
    {
        return FixedAsset::query()
            ->with(['category'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('asset_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $categoryId) => $query->where('fixed_asset_category_id', $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('asset_number');
    }

    private function depreciationScheduleQuery(array $filters): Builder
    {
        return FixedAssetDepreciationSchedule::query()
            ->with(['asset.category', 'financialPeriod.fiscalYear', 'depreciationRun', 'journalEntry'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->whereHas('asset', function (Builder $assetQuery) use ($search): void {
                    $assetQuery->where('asset_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('period_start_date')
            ->orderBy('period_number')
            ->orderBy('fixed_asset_id');
    }

    private function depreciationRunQuery(array $filters): Builder
    {
        return FixedAssetDepreciationRun::query()
            ->with(['financialPeriod.fiscalYear', 'journalEntry'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['period_id'] ?? null, fn (Builder $query, string $periodId) => $query->where('financial_period_id', $periodId))
            ->orderByDesc('run_date')
            ->orderByDesc('number');
    }

    private function disposalQuery(array $filters): Builder
    {
        return FixedAssetDisposal::query()
            ->with(['asset.category', 'financialPeriod.fiscalYear', 'journalEntry', 'reversalJournalEntry'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhereHas('asset', function (Builder $assetQuery) use ($search): void {
                            $assetQuery->where('asset_number', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['disposal_type'] ?? null, fn (Builder $query, string $type) => $query->where('disposal_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('disposal_date')
            ->orderByDesc('number');
    }

    private function assetRows(Collection $assets): Collection
    {
        $postedDepreciation = $this->postedDepreciationByAsset($assets->pluck('id')->all());

        return $assets
            ->map(fn (FixedAsset $asset): array => $this->assetRow($asset, (int) ($postedDepreciation[$asset->id] ?? 0)))
            ->values();
    }

    /**
     * @param  list<string>  $assetIds
     * @return array<string, int>
     */
    private function postedDepreciationByAsset(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return FixedAssetDepreciationSchedule::query()
            ->select('fixed_asset_id')
            ->selectRaw('COALESCE(SUM(depreciation_minor), 0) as posted_minor')
            ->whereIn('fixed_asset_id', $assetIds)
            ->where('status', 'posted')
            ->groupBy('fixed_asset_id')
            ->pluck('posted_minor', 'fixed_asset_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    private function assetRow(FixedAsset $asset, int $postedDepreciationMinor): array
    {
        $openingAccumulatedMinor = (int) $asset->opening_accumulated_depreciation_minor;
        $costMinor = (int) $asset->cost_minor;
        $totalAccumulatedMinor = $openingAccumulatedMinor + $postedDepreciationMinor;
        $netBookValueMinor = max(0, $costMinor - $totalAccumulatedMinor);

        return [
            'id' => $asset->id,
            'asset_number' => $asset->asset_number,
            'name' => $this->translations($asset, 'name'),
            'category' => $asset->category ? [
                'id' => $asset->category->id,
                'code' => $asset->category->code,
                'name' => $this->translations($asset->category, 'name'),
            ] : null,
            'currency' => $asset->currency,
            'acquisition_date' => $this->dateString($asset->acquisition_date),
            'in_service_date' => $this->dateString($asset->in_service_date),
            'useful_life_months' => (int) $asset->useful_life_months,
            'cost_minor' => $costMinor,
            'salvage_value_minor' => (int) $asset->salvage_value_minor,
            'opening_accumulated_depreciation_minor' => $openingAccumulatedMinor,
            'posted_accumulated_depreciation_minor' => $postedDepreciationMinor,
            'total_accumulated_depreciation_minor' => $totalAccumulatedMinor,
            'net_book_value_minor' => $netBookValueMinor,
            'status' => $asset->status,
        ];
    }

    private function depreciationScheduleRow(FixedAssetDepreciationSchedule $schedule): array
    {
        $asset = $schedule->asset;
        $period = $schedule->financialPeriod;

        return [
            'id' => $schedule->id,
            'period_number' => (int) $schedule->period_number,
            'period_start_date' => $this->dateString($schedule->period_start_date),
            'period_end_date' => $this->dateString($schedule->period_end_date),
            'depreciation_minor' => (int) $schedule->depreciation_minor,
            'accumulated_depreciation_minor' => (int) $schedule->accumulated_depreciation_minor,
            'net_book_value_minor' => (int) $schedule->net_book_value_minor,
            'status' => $schedule->status,
            'asset' => $asset ? [
                'id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'name' => $this->translations($asset, 'name'),
                'currency' => $asset->currency,
                'category' => $asset->category ? [
                    'id' => $asset->category->id,
                    'code' => $asset->category->code,
                    'name' => $this->translations($asset->category, 'name'),
                ] : null,
            ] : null,
            'financial_period' => $period ? [
                'id' => $period->id,
                'year' => $period->fiscalYear?->year,
                'month' => (int) $period->month,
                'start_date' => $this->dateString($period->start_date),
                'end_date' => $this->dateString($period->end_date),
                'status' => $period->status,
            ] : null,
            'depreciation_run_number' => $schedule->depreciationRun?->number,
            'journal_number' => $schedule->journalEntry?->number,
        ];
    }

    private function depreciationRunRow(FixedAssetDepreciationRun $run): array
    {
        $period = $run->financialPeriod;

        return [
            'id' => $run->id,
            'number' => $run->number,
            'run_date' => $this->dateString($run->run_date),
            'total_depreciation_minor' => (int) $run->total_depreciation_minor,
            'asset_count' => (int) $run->asset_count,
            'status' => $run->status,
            'financial_period' => $period ? [
                'id' => $period->id,
                'year' => $period->fiscalYear?->year,
                'month' => (int) $period->month,
                'start_date' => $this->dateString($period->start_date),
                'end_date' => $this->dateString($period->end_date),
                'status' => $period->status,
            ] : null,
            'journal_number' => $run->journalEntry?->number,
        ];
    }

    private function disposalRow(FixedAssetDisposal $disposal): array
    {
        $asset = $disposal->asset;
        $period = $disposal->financialPeriod;

        return [
            'id' => $disposal->id,
            'number' => $disposal->number,
            'disposal_date' => $this->dateString($disposal->disposal_date),
            'disposal_type' => $disposal->disposal_type,
            'proceeds_minor' => (int) $disposal->proceeds_minor,
            'net_book_value_minor' => (int) $disposal->net_book_value_minor,
            'gain_minor' => (int) $disposal->gain_minor,
            'loss_minor' => (int) $disposal->loss_minor,
            'status' => $disposal->status,
            'asset' => $asset ? [
                'id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'name' => $this->translations($asset, 'name'),
                'currency' => $asset->currency,
                'category' => $asset->category ? [
                    'id' => $asset->category->id,
                    'code' => $asset->category->code,
                    'name' => $this->translations($asset->category, 'name'),
                ] : null,
            ] : null,
            'financial_period' => $period ? [
                'id' => $period->id,
                'year' => $period->fiscalYear?->year,
                'month' => (int) $period->month,
                'start_date' => $this->dateString($period->start_date),
                'end_date' => $this->dateString($period->end_date),
                'status' => $period->status,
            ] : null,
            'journal_number' => $disposal->journalEntry?->number,
            'reversal_journal_number' => $disposal->reversalJournalEntry?->number,
        ];
    }

    private function translations(object $model, string $field): array|string|null
    {
        if (method_exists($model, 'getTranslations')) {
            return $model->getTranslations($field);
        }

        return $model->{$field} ?? null;
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (method_exists($value, 'format')) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
