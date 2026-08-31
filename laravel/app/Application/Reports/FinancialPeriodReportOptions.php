<?php

namespace App\Application\Reports;

use App\Models\FinancialPeriod;
use Illuminate\Support\Collection;

class FinancialPeriodReportOptions
{
    /**
     * @return Collection<int, array{id: string, year: int|null, month: int, start_date: string|null, end_date: string|null, status: string}>
     */
    public function all(): Collection
    {
        return FinancialPeriod::query()
            ->with('fiscalYear')
            ->orderBy('start_date', 'desc')
            ->get(['id', 'fiscal_year_id', 'month', 'start_date', 'end_date', 'status'])
            ->map(fn (FinancialPeriod $period): array => [
                'id' => $period->id,
                'year' => $period->fiscalYear?->year,
                'month' => $period->month,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date' => $period->end_date?->format('Y-m-d'),
                'status' => $period->status,
            ])
            ->values();
    }
}
