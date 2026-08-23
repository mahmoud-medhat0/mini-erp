<?php

namespace App\Application\Accounting;

use App\Domain\Accounting\PeriodClosedException;
use App\Models\FinancialPeriod;
use Carbon\Carbon;
use InvalidArgumentException;

class PeriodGuard
{
    /**
     * Ensure the target financial period exists, is open/reopened, and the posting date falls within period bounds.
     *
     * @throws PeriodClosedException|InvalidArgumentException
     */
    public function assertPeriodOpenForPosting(?string $periodId, ?string $postingDate = null): FinancialPeriod
    {
        if (! $periodId) {
            throw new PeriodClosedException('Financial period ID is required for posting.');
        }

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()->where('id', $periodId)->first();

        if (! $period) {
            throw new InvalidArgumentException("Financial period {$periodId} does not exist.");
        }

        if (! $period->isOpen()) {
            throw new PeriodClosedException("Target financial period {$periodId} is closed or locked.", periodId: $period->id, date: $postingDate);
        }

        if ($postingDate !== null && $postingDate !== '') {
            $pDate = Carbon::parse($postingDate)->toDateString();
            $pStart = Carbon::parse($period->start_date)->toDateString();
            $pEnd = Carbon::parse($period->end_date)->toDateString();

            if ($pDate < $pStart || $pDate > $pEnd) {
                throw new InvalidArgumentException("Posting date {$pDate} is outside target financial period bounds ({$pStart} to {$pEnd}).");
            }
        }

        return $period;
    }

    /**
     * Ensure the target financial period exists and is open/reopened with row-level pessimistic lock (lockForUpdate).
     *
     * @throws PeriodClosedException|InvalidArgumentException
     */
    public function assertPeriodOpenForPostingWithLock(string $periodId, ?string $postingDate = null): FinancialPeriod
    {
        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('id', $periodId)
            ->lockForUpdate()
            ->first();

        if (! $period) {
            throw new InvalidArgumentException("Financial period {$periodId} does not exist.");
        }

        if (! $period->isOpen()) {
            throw new PeriodClosedException("Target financial period {$periodId} is closed or locked.", periodId: $period->id, date: $postingDate);
        }

        if ($postingDate !== null && $postingDate !== '') {
            $pDate = Carbon::parse($postingDate)->toDateString();
            $pStart = Carbon::parse($period->start_date)->toDateString();
            $pEnd = Carbon::parse($period->end_date)->toDateString();

            if ($pDate < $pStart || $pDate > $pEnd) {
                throw new InvalidArgumentException("Posting date {$pDate} is outside target financial period bounds ({$pStart} to {$pEnd}).");
            }
        }

        return $period;
    }

    /**
     * Resolve the financial period covering a posting date and lock it before side effects begin.
     *
     * @throws PeriodClosedException|InvalidArgumentException
     */
    public function resolveOpenPeriodForPostingDateWithLock(string $postingDate): FinancialPeriod
    {
        $pDate = Carbon::parse($postingDate)->toDateString();

        /** @var FinancialPeriod|null $period */
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', $pDate)
            ->where('end_date', '>=', $pDate)
            ->lockForUpdate()
            ->first();

        if (! $period) {
            throw new InvalidArgumentException("No financial period covers posting date {$pDate}.");
        }

        if (! $period->isOpen()) {
            throw new PeriodClosedException("Target financial period {$period->id} is closed or locked.", periodId: $period->id, date: $pDate);
        }

        return $period;
    }
}
