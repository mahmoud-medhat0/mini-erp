<?php

namespace App\Application\Accounting;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;

class CustomerOpeningBalancePageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return [
            'balances' => CustomerOpeningBalance::query()
                ->with(['customer', 'fiscalYear', 'financialPeriod'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->where('is_closed', false)->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->where('is_closed', false)->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }
}
