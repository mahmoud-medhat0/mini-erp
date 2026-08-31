<?php

namespace App\Application\FixedAssets;

use App\Models\FinancialPeriod;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\User;

class FixedAssetDepreciationRunPageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(?User $user): array
    {
        return [
            'runs' => FixedAssetDepreciationRun::query()
                ->with(['financialPeriod', 'journalEntry', 'poster'])
                ->orderByDesc('created_at')
                ->paginate(15),
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

    /**
     * @return array<string, mixed>
     */
    public function showData(string $id, ?User $user): array
    {
        $run = FixedAssetDepreciationRun::query()
            ->with(['financialPeriod', 'journalEntry', 'poster', 'schedules.asset.category'])
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

        $schedules = FixedAssetDepreciationSchedule::query()
            ->with(['asset.category'])
            ->where('financial_period_id', $period->id)
            ->where('status', 'planned')
            ->whereHas('asset', function ($query): void {
                $query->where('status', 'active');
            })
            ->orderBy('id')
            ->get();

        return [
            'period' => $period,
            'schedules' => $schedules,
            'totalDepreciationMinor' => (int) $schedules->sum('depreciation_minor'),
            'assetCount' => $schedules->pluck('fixed_asset_id')->unique()->count(),
            'can' => [
                'post' => $this->can($user, 'fixedAssets.post') && $this->can($user, 'view_financials'),
            ],
        ];
    }

    private function can(?User $user, string $permission): bool
    {
        return $user?->can($permission) ?? false;
    }
}
