<?php

namespace App\Application\FixedAssets;

use App\Application\Accounting\PeriodService;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FixedAssetDepreciationEngineService
{
    /**
     * Generate or regenerate depreciation schedule for a fixed asset atomically & idempotently.
     */
    public function generateSchedule(string $assetId): Collection
    {
        return DB::transaction(function () use ($assetId): Collection {
            /** @var FixedAsset $asset */
            $asset = FixedAsset::query()->lockForUpdate()->findOrFail($assetId);

            if ($asset->status !== 'active') {
                throw ValidationException::withMessages([
                    'asset' => ['Only active fixed assets can have depreciation schedules.'],
                ]);
            }

            if ($asset->depreciation_method !== 'straight_line') {
                throw ValidationException::withMessages([
                    'depreciation_method' => ['Only straight-line depreciation is supported in Phase 6.'],
                ]);
            }

            if ($asset->useful_life_months <= 0) {
                throw ValidationException::withMessages([
                    'useful_life_months' => ['Asset useful life in months must be greater than zero.'],
                ]);
            }

            $cost = $asset->cost_minor;
            $salvage = max(0, $asset->salvage_value_minor);
            $openingAccum = max(0, $asset->opening_accumulated_depreciation_minor);
            $depreciableBase = max(0, $cost - $salvage - $openingAccum);
            $usefulLifeMonths = $asset->useful_life_months;

            // Fetch existing schedules
            $existingSchedules = FixedAssetDepreciationSchedule::query()
                ->where('fixed_asset_id', $asset->id)
                ->orderBy('period_number')
                ->get();

            $postedSchedules = $existingSchedules->where('status', 'posted');

            // If all lines are posted, schedule is locked
            if ($postedSchedules->count() >= $usefulLifeMonths) {
                return $existingSchedules;
            }

            // Owner policy: depreciation starts in the month after the asset enters service.
            $startDateStr = $this->depreciationStartDate($asset);
            $startCarbon = Carbon::parse($startDateStr);

            // Auto-ensure fiscal years exist for the full useful life duration
            $startYear = $startCarbon->year;
            $yearsNeeded = (int) ceil(($usefulLifeMonths + 11) / 12) + 1;
            for ($y = 0; $y <= $yearsNeeded; $y++) {
                $this->ensureFiscalYearPeriods($startYear + $y);
            }

            // Find starting financial period
            $startPeriod = FinancialPeriod::query()
                ->where('start_date', '<=', $startDateStr)
                ->where('end_date', '>=', $startDateStr)
                ->orderBy('start_date')
                ->first();

            if (! $startPeriod) {
                $startPeriod = FinancialPeriod::query()
                    ->where('start_date', '>=', $startDateStr)
                    ->orderBy('start_date')
                    ->first();
            }

            if (! $startPeriod) {
                throw ValidationException::withMessages([
                    'financial_periods' => ['No financial period is available for the depreciation schedule start date.'],
                ]);
            }

            $startPeriodStartDate = Carbon::parse($startPeriod->start_date)->toDateString();

            // Fetch required sequence of financial periods
            $periods = FinancialPeriod::query()
                ->where('start_date', '>=', $startPeriodStartDate)
                ->orderBy('start_date')
                ->take($usefulLifeMonths)
                ->get();

            if ($periods->count() < $usefulLifeMonths) {
                throw ValidationException::withMessages([
                    'financial_periods' => ["Insufficient fiscal periods configured. Required: [{$usefulLifeMonths}], Available: [{$periods->count()}]."],
                ]);
            }

            // Calculate integer minor-unit depreciation per month
            $monthlyBase = intdiv($depreciableBase, $usefulLifeMonths);
            $remainder = $depreciableBase % $usefulLifeMonths;

            $currentAccumulated = $openingAccum;
            $newSchedules = collect();

            for ($i = 1; $i <= $usefulLifeMonths; $i++) {
                /** @var FinancialPeriod $period */
                $period = $periods->get($i - 1);

                // Check if this period already has a posted schedule
                $existingPosted = $postedSchedules->firstWhere('period_number', $i);
                if ($existingPosted) {
                    $currentAccumulated = $existingPosted->accumulated_depreciation_minor;
                    $newSchedules->push($existingPosted);

                    continue;
                }

                $periodDepreciation = $monthlyBase + ($i <= $remainder ? 1 : 0);
                $currentAccumulated += $periodDepreciation;
                $netBookValue = max($salvage, $cost - $currentAccumulated);

                $schedule = FixedAssetDepreciationSchedule::query()->updateOrCreate(
                    [
                        'fixed_asset_id' => $asset->id,
                        'period_number' => $i,
                    ],
                    [
                        'financial_period_id' => $period->id,
                        'period_start_date' => $period->start_date,
                        'period_end_date' => $period->end_date,
                        'depreciation_minor' => $periodDepreciation,
                        'accumulated_depreciation_minor' => $currentAccumulated,
                        'net_book_value_minor' => $netBookValue,
                        'status' => 'planned',
                    ]
                );

                $newSchedules->push($schedule);
            }

            return $newSchedules;
        });
    }

    /**
     * Read the existing schedule without generating or mutating rows.
     */
    public function getSchedule(string $assetId): Collection
    {
        return FixedAssetDepreciationSchedule::query()
            ->with(['financialPeriod', 'journalEntry'])
            ->where('fixed_asset_id', $assetId)
            ->orderBy('period_number')
            ->get();
    }

    private function depreciationStartDate(FixedAsset $asset): string
    {
        $baseDate = $asset->in_service_date
            ? Carbon::parse($asset->in_service_date->format('Y-m-d'))
            : now();

        return Carbon::create(
            (int) $baseDate->format('Y'),
            (int) $baseDate->format('m'),
            1
        )->addMonthNoOverflow()->toDateString();
    }

    private function ensureFiscalYearPeriods(int $year): void
    {
        /** @var FiscalYear|null $fiscalYear */
        $fiscalYear = FiscalYear::query()->where('year', $year)->first();

        if (! $fiscalYear) {
            /** @var PeriodService $periodService */
            $periodService = app(PeriodService::class);
            $periodService->createFiscalYear(
                $year,
                "{$year}-01-01",
                "{$year}-12-31"
            );

            return;
        }

        $start = Carbon::parse($fiscalYear->start_date);

        for ($month = 1; $month <= 12; $month++) {
            $exists = FinancialPeriod::query()
                ->where('fiscal_year_id', $fiscalYear->id)
                ->where('month', $month)
                ->exists();

            if ($exists) {
                continue;
            }

            $periodStart = $start->copy()->addMonths($month - 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            FinancialPeriod::query()->create([
                'id' => (string) Str::uuid(),
                'fiscal_year_id' => $fiscalYear->id,
                'month' => $month,
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'status' => 'open',
            ]);
        }
    }
}
