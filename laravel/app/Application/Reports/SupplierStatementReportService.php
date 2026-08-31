<?php

namespace App\Application\Reports;

use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\SupplierOpeningBalance;
use App\Models\SupplierPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierStatementReportService
{
    public function __construct(
        private readonly ReportCurrencyResolver $currencyResolver,
    ) {}

    public function generate(string $supplierId, string $dateFrom, string $dateTo, ?string $currency = null): array
    {
        $supplier = Supplier::query()->findOrFail($supplierId);
        $targetCurrency = $this->currencyResolver->resolve($currency);

        // 1. Calculate opening balance before dateFrom (Opening Balance + Payable Entries - Supplier Payments)
        $obPrior = (int) SupplierOpeningBalance::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->where('entry_date', '<', $dateFrom)
            ->sum('amount_minor');

        $payEntriesPrior = (int) PayableEntry::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->where('entry_date', '<', $dateFrom)
            ->sum(DB::raw('credit_minor - debit_minor'));

        $paymentsPrior = (int) SupplierPayment::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->where('payment_date', '<', $dateFrom)
            ->sum('amount_minor');

        $openingBalanceMinor = $obPrior + $payEntriesPrior - $paymentsPrior;

        // 2. Fetch movement lines inside dateFrom..dateTo
        $movements = new Collection;

        // Posted Opening Balances within range
        $obs = SupplierOpeningBalance::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->get();

        foreach ($obs as $ob) {
            $movements->push([
                'date' => $ob->entry_date,
                'type' => 'Opening Balance',
                'reference' => $ob->reference ?? 'OB-'.$ob->id,
                'description' => $ob->description ?? 'Supplier Opening Balance',
                'debit_minor' => 0,
                'credit_minor' => (int) $ob->amount_minor,
                'created_at' => $ob->created_at,
            ]);
        }

        // Payable Entries within range
        $payEntries = PayableEntry::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->whereBetween('entry_date', [$dateFrom, $dateTo])
            ->get();

        foreach ($payEntries as $pe) {
            $movements->push([
                'date' => $pe->entry_date,
                'type' => 'Payable Entry',
                'reference' => 'PE-'.$pe->id,
                'description' => $pe->description ?? 'Payable Entry',
                'debit_minor' => (int) $pe->debit_minor,
                'credit_minor' => (int) $pe->credit_minor,
                'created_at' => $pe->created_at,
            ]);
        }

        // Supplier Payments within range
        $payments = SupplierPayment::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $targetCurrency)
            ->where('status', 'posted')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->get();

        foreach ($payments as $pm) {
            $movements->push([
                'date' => $pm->payment_date,
                'type' => 'Supplier Payment',
                'reference' => $pm->number,
                'description' => $pm->description ?? 'Supplier Payment',
                'debit_minor' => (int) $pm->amount_minor,
                'credit_minor' => 0,
                'created_at' => $pm->created_at,
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
            $runningBalance += ($cr - $dr);

            $totalDebit += $dr;
            $totalCredit += $cr;

            $lines[] = array_merge($item, [
                'running_balance_minor' => $runningBalance,
            ]);
        }

        return [
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'tax_number' => $supplier->tax_number,
                'phone' => $supplier->phone,
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
