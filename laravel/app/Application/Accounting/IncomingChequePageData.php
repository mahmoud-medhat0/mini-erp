<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\IncomingCheque;

class IncomingChequePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $status = $filters['status'] ?? null;
        $customerId = $filters['customer_id'] ?? null;

        $query = IncomingCheque::query()
            ->with(['customer', 'depositBankAccount']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return [
            'cheques' => $query->orderBy('due_date', 'asc')
                ->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString(),
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'filters' => [
                'status' => $status,
                'customer_id' => $customerId,
            ],
        ];
    }
}
