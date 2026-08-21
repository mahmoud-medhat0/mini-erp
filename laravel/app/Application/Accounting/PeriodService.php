<?php

namespace App\Application\Accounting;

use App\Domain\Audit\AuditLogger;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PeriodService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createFiscalYear(int $year, string $startDate, string $endDate): FiscalYear
    {
        return DB::transaction(function () use ($year, $startDate, $endDate): FiscalYear {
            $exists = FiscalYear::query()->where('year', $year)->exists();
            if ($exists) {
                throw new InvalidArgumentException(__('Fiscal year :year already exists.', ['year' => $year]));
            }

            $fiscalYear = FiscalYear::create([
                'id' => (string) Str::uuid(),
                'year' => $year,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'open',
            ]);

            // Generate 12 monthly periods
            $start = Carbon::parse($startDate);
            for ($month = 1; $month <= 12; $month++) {
                $periodStart = $start->copy()->addMonths($month - 1)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();

                FinancialPeriod::create([
                    'id' => (string) Str::uuid(),
                    'fiscal_year_id' => $fiscalYear->id,
                    'month' => $month,
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'status' => 'open',
                ]);
            }

            return $fiscalYear->fresh(['periods']);
        });
    }

    public function closePeriod(FinancialPeriod $period, int $userId): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $userId): FinancialPeriod {
            $lockedPeriod = FinancialPeriod::query()
                ->where('id', $period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPeriod->status === 'closed') {
                return $lockedPeriod;
            }

            $before = $lockedPeriod->toArray();
            $lockedPeriod->update([
                'status' => 'closed',
            ]);

            $this->auditLogger->record($userId, 'financial_period.close', 'financial_period', (string) $lockedPeriod->id, before: $before, after: $lockedPeriod->toArray());

            return $lockedPeriod;
        });
    }

    public function reopenPeriod(FinancialPeriod $period, int $userId): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $userId): FinancialPeriod {
            $lockedPeriod = FinancialPeriod::query()
                ->where('id', $period->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $lockedPeriod->toArray();
            $lockedPeriod->update([
                'status' => 'reopened',
            ]);

            $this->auditLogger->record($userId, 'financial_period.reopen', 'financial_period', (string) $lockedPeriod->id, before: $before, after: $lockedPeriod->toArray());

            return $lockedPeriod;
        });
    }
}
