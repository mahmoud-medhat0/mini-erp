<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\SupplierPayment;

class SupplierPaymentPageData
{
    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        return [
            'payments' => SupplierPayment::query()
                ->with(['supplier', 'cashAccount', 'bankAccount', 'fiscalYear', 'financialPeriod'])
                ->orderBy('created_at', 'desc')
                ->paginate(15),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->where('is_closed', false)->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->where('is_closed', false)->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }
}
