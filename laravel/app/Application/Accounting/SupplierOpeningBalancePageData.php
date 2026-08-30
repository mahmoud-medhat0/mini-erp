<?php

namespace App\Application\Accounting;

use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\SupplierOpeningBalance;

class SupplierOpeningBalancePageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return [
            'balances' => SupplierOpeningBalance::query()
                ->with(['supplier', 'fiscalYear', 'financialPeriod'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }
}
