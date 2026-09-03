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
        $locale = app()->getLocale();

        $paginator = SupplierOpeningBalance::query()
            ->with(['supplier', 'fiscalYear', 'financialPeriod'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Build explicit row arrays so Spatie's toArray() cannot emit the
        // raw translations object for the `name` field.
        $rows = $paginator->getCollection()->map(function ($balance) use ($locale) {
            $supplier = null;

            if ($balance->supplier) {
                $supplier = [
                    'id' => $balance->supplier->id,
                    'code' => $balance->supplier->code,
                    'name' => $balance->supplier->getTranslation('name', $locale, false)
                        ?: ($balance->supplier->getTranslation('name', 'en', false) ?: $balance->supplier->code),
                ];
            }

            return [
                'id' => $balance->id,
                'supplier_id' => $balance->supplier_id,
                'supplier' => $supplier,
                'fiscal_year_id' => $balance->fiscal_year_id,
                'financial_period_id' => $balance->financial_period_id,
                'entry_date' => $balance->entry_date,
                'reference' => $balance->reference,
                'currency' => $balance->currency,
                'amount_minor' => $balance->amount_minor,
                'status' => $balance->status,
                'posted_at' => $balance->posted_at,
                'created_at' => $balance->created_at,
            ];
        });

        $paginator->setCollection($rows);

        $suppliers = Supplier::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->getTranslation('name', $locale, false)
                    ?: ($s->getTranslation('name', 'en', false) ?: $s->code),
            ]);

        return [
            'balances' => $paginator,
            'suppliers' => $suppliers,
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }
}
