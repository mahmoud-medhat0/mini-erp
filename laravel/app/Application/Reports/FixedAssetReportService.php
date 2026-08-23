<?php

namespace App\Application\Reports;

use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\FixedAssetDisposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FixedAssetReportService
{
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
