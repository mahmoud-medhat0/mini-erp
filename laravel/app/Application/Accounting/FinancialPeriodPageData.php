<?php

namespace App\Application\Accounting;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class FinancialPeriodPageData
{
    /**
     * @return array{fiscalYears: EloquentCollection<int, FiscalYear>}
     */
    public function indexData(): array
    {
        return [
            'fiscalYears' => FiscalYear::query()
                ->with('periods')
                ->orderBy('year', 'desc')
                ->get(),
        ];
    }
}
