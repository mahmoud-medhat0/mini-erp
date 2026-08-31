<?php

namespace App\Application\Reports;

use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\CustomerReceipt;
use App\Models\ReceivableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerStatementReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(string $customerId, string $dateFrom, string $dateTo, ?string $currency = null): array
    {
        $customer = Customer::query()->findOrFail($customerId);
        $targetCurrency = $this->currencyResolver->resolve($currency);

        // 1. Calculate opening balance before dateFrom (Opening Balance + Receivable Entries - Customer Receipts)
        $obPrior = (int) CustomerOpeningBalance::query()
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->where('entry_date', '<', $dateFrom)
            ->sum('amount_minor');

        $recEntriesPrior = (int) ReceivableEntry::query()
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<', $dateFrom)
            ->sum(DB::raw('debit_minor - credit_minor'));

        $receiptsPrior = (int) CustomerReceipt::query()
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->where('receipt_date', '<', $dateFrom)
            ->sum('amount_minor');

        $openingBalanceMinor = $obPrior + $recEntriesPrior - $receiptsPrior;

        // 2. Fetch movement lines inside dateFrom..dateTo
        $movements = new Collection;

        // Posted Opening Balances within range
        $obs = CustomerOpeningBalance::query()
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->get();

        foreach ($obs as $ob) {
            $movements->push([
                'date' => $ob->entry_date,
                'type' => 'Opening Balance',
                'reference' => $ob->reference ?? 'OB-'.$ob->id,
                'description' => $ob->description ?? 'Customer Opening Balance',
                'debit_minor' => (int) $ob->amount_minor,
                'credit_minor' => 0,
                'created_at' => $ob->created_at,
            ]);
        }

        // Receivable Entries within range
        $recEntries = ReceivableEntry::query()
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->get();

        foreach ($recEntries as $re) {
            $movements->push([
                'date' => $re->entry_date,
                'type' => 'Receivable Entry',
                'reference' => 'RE-'.$re->id,
                'description' => $re->description ?? 'Receivable Entry',
                'debit_minor' => (int) $re->debit_minor,
                'credit_minor' => (int) $re->credit_minor,
                'created_at' => $re->created_at,
            ]);
        }

        // Customer Receipts within range
        $receipts = CustomerReceipt::query()
            ->where('customer_id', $customerId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->whereBetween('receipt_date', [$dateFrom, $dateTo])
            ->get();

        foreach ($receipts as $rc) {
            $movements->push([
                'date' => $rc->receipt_date,
                'type' => 'Customer Receipt',
                'reference' => $rc->number,
                'description' => $rc->description ?? 'Customer Receipt',
                'debit_minor' => 0,
                'credit_minor' => (int) $rc->amount_minor,
                'created_at' => $rc->created_at,
            ]);
        }

        // Sort movements chronologically
        $sorted = $movements->sortBy(fn ($item) => $item['date'].' '.$item['created_at'])->values();

        $runningBalance = $openingBalanceMinor;
        $totalDebit = 0;
        $totalCredit = 0;
        $lines = [];

        foreach ($sorted as $item) {
            $dr = $item['debit_minor'];
            $cr = $item['credit_minor'];
            $runningBalance += ($dr - $cr);

            $totalDebit += $dr;
            $totalCredit += $cr;

            $lines[] = array_merge($item, [
                'running_balance_minor' => $runningBalance,
            ]);
        }

        return [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'tax_number' => $customer->tax_number,
                'phone' => $customer->phone,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $targetCurrency,
            ],
            'opening_balance_minor' => $openingBalanceMinor,
            'lines' => $lines,
            'total_debit_minor' => $totalDebit,
            'total_credit_minor' => $totalCredit,
            'closing_balance_minor' => $runningBalance,
        ];
    }
}
