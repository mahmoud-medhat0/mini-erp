<?php

namespace App\Application\FixedAssets;

use App\Models\FinancialPeriod;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FixedAssetDepreciationRunPageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(?User $user): array
    {
        return [
            'runs' => [],
            'openPeriods' => FinancialPeriod::query()
                ->whereIn('status', ['open', 'reopened'])
                ->orderBy('start_date')
                ->get(),
            'can' => [
                'post' => $this->can($user, 'fixedAssets.post') && $this->can($user, 'view_financials'),
                'reverse' => $this->can($user, 'fixedAssets.reverse') && $this->can($user, 'view_financials'),
            ],
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = FixedAssetDepreciationRun::query()
            ->with(['financialPeriod', 'journalEntry', 'poster'])
            ->select('fixed_asset_depreciation_run.*');

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $builder->where(function (Builder $nested) use ($search): void {
                    $like = "%{$search}%";
                    $nested
                        ->where('fixed_asset_depreciation_run.number', 'like', $like)
                        ->orWhere('fixed_asset_depreciation_run.status', 'like', $like)
                        ->orWhereHas('financialPeriod', fn (Builder $period) => $period
                            ->where('start_date', 'like', $like)
                            ->orWhere('end_date', 'like', $like))
                        ->orWhereHas('poster', fn (Builder $poster) => $poster->where('name', 'like', $like));
                });
            })
            ->orderColumn('number', 'fixed_asset_depreciation_run.number $1')
            ->orderColumn('run_date', 'fixed_asset_depreciation_run.run_date $1')
            ->orderColumn('asset_count', 'fixed_asset_depreciation_run.asset_count $1')
            ->orderColumn('total_depreciation_minor', 'fixed_asset_depreciation_run.total_depreciation_minor $1')
            ->orderColumn('status', 'fixed_asset_depreciation_run.status $1')
            ->addColumn('actions', fn (): null => null)
            ->toJson();
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(string $id, ?User $user): array
    {
        $run = FixedAssetDepreciationRun::query()
            ->with(['financialPeriod', 'journalEntry', 'poster'])
            ->findOrFail($id);

        return [
            'run' => $run,
            'can' => [
                'reverse' => $this->can($user, 'fixedAssets.reverse') && $this->can($user, 'view_financials') && $run->status === 'posted',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewData(string $financialPeriodId, ?User $user): array
    {
        $period = FinancialPeriod::query()->findOrFail($financialPeriodId);

        $scheduleQuery = FixedAssetDepreciationSchedule::query()
            ->where('financial_period_id', $period->id)
            ->where('status', 'planned')
            ->whereHas('asset', function ($query): void {
                $query->where('status', 'active');
            });
        $totals = (clone $scheduleQuery)
            ->selectRaw('COUNT(DISTINCT fixed_asset_id) as asset_count')
            ->selectRaw('COALESCE(SUM(depreciation_minor), 0) as total_depreciation_minor')
            ->first();
        $assetCount = (int) ($totals?->asset_count ?? 0);

        return [
            'period' => $period,
            // Compatibility presence marker; rows are served by the DataTable feed.
            'schedules' => $assetCount > 0 ? [['id' => 'available']] : [],
            'totalDepreciationMinor' => (int) ($totals?->total_depreciation_minor ?? 0),
            'assetCount' => $assetCount,
            'can' => [
                'post' => $this->can($user, 'fixedAssets.post') && $this->can($user, 'view_financials'),
            ],
        ];
    }

    public function runSchedulesData(string $runId): JsonResponse
    {
        FixedAssetDepreciationRun::query()->whereKey($runId)->firstOrFail();

        return $this->schedulesDataTable(
            FixedAssetDepreciationSchedule::query()->where('depreciation_run_id', $runId),
        );
    }

    public function previewSchedulesData(string $financialPeriodId): JsonResponse
    {
        FinancialPeriod::query()->whereKey($financialPeriodId)->firstOrFail();

        return $this->schedulesDataTable(
            FixedAssetDepreciationSchedule::query()
                ->where('fixed_asset_depreciation_schedule.financial_period_id', $financialPeriodId)
                ->where('fixed_asset_depreciation_schedule.status', 'planned')
                ->whereHas('asset', fn (Builder $builder) => $builder->where('status', 'active')),
        );
    }

    private function schedulesDataTable(Builder $query): JsonResponse
    {
        $query
            ->leftJoin('fixed_asset', 'fixed_asset.id', '=', 'fixed_asset_depreciation_schedule.fixed_asset_id')
            ->leftJoin('fixed_asset_category', 'fixed_asset_category.id', '=', 'fixed_asset.fixed_asset_category_id')
            ->select([
                'fixed_asset_depreciation_schedule.*',
                'fixed_asset.asset_number as asset_number',
                'fixed_asset.name as asset_name',
                'fixed_asset_category.name as category_name',
            ]);

        return DataTables::eloquent($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw("LOWER(COALESCE(fixed_asset.asset_number, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(CAST(fixed_asset.name AS TEXT), '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(CAST(fixed_asset_category.name AS TEXT), '')) LIKE ?", [$like]);
                });
            })
            ->orderColumn('asset_number', 'fixed_asset.asset_number $1')
            ->orderColumn('asset_name', 'fixed_asset.asset_number $1')
            ->orderColumn('category_name', 'fixed_asset_category.name $1')
            ->orderColumn('period_number', 'fixed_asset_depreciation_schedule.period_number $1')
            ->orderColumn('depreciation_minor', 'fixed_asset_depreciation_schedule.depreciation_minor $1')
            ->orderColumn('accumulated_depreciation_minor', 'fixed_asset_depreciation_schedule.accumulated_depreciation_minor $1')
            ->orderColumn('net_book_value_minor', 'fixed_asset_depreciation_schedule.net_book_value_minor $1')
            ->editColumn('asset_name', fn (object $row) => $this->decodeTranslations($row->asset_name))
            ->editColumn('category_name', fn (object $row) => $this->decodeTranslations($row->category_name))
            // Prevent Yajra's default escaping from turning e.g. "&" into "&amp;" inside the decoded {en, ar} maps.
            ->rawColumns(['asset_name', 'asset_name.en', 'asset_name.ar', 'category_name', 'category_name.en', 'category_name.ar'])
            ->toJson();
    }

    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }

    private function can(?User $user, string $permission): bool
    {
        return $user?->can($permission) ?? false;
    }
}
