<?php

namespace App\Application\Taxes;

use App\Models\TaxPeriod;
use Illuminate\Validation\ValidationException;

class TaxPeriodGuard
{
    public function ensureDateNotFiled(string $documentDate): void
    {
        $filedPeriod = TaxPeriod::query()
            ->where('status', 'filed')
            ->where('start_date', '<=', $documentDate)
            ->where('end_date', '>=', $documentDate)
            ->first();

        if ($filedPeriod !== null) {
            $startDate = is_string($filedPeriod->start_date) ? $filedPeriod->start_date : $filedPeriod->start_date->format('Y-m-d');
            $endDate = is_string($filedPeriod->end_date) ? $filedPeriod->end_date : $filedPeriod->end_date->format('Y-m-d');

            throw ValidationException::withMessages([
                'tax_period' => ["Tax-affecting postings are blocked because tax period '{$filedPeriod->period_label}' ({$startDate} to {$endDate}) is filed."],
            ]);
        }
    }
}
