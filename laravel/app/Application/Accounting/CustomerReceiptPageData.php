<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;

class CustomerReceiptPageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return [
            'receipts' => CustomerReceipt::query()
                ->with(['customer', 'cashAccount', 'bankAccount', 'fiscalYear', 'financialPeriod'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }
}
