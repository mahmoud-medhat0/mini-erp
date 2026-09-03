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
        $locale = app()->getLocale();

        $paginator = CustomerOpeningBalance::query()
            ->with(['customer', 'fiscalYear', 'financialPeriod'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Build explicit row arrays so Spatie's toArray() cannot emit the
        // raw translations object for the `name` field.
        $rows = $paginator->getCollection()->map(function ($balance) use ($locale) {
            $customer = null;

            if ($balance->customer) {
                $customer = [
                    'id' => $balance->customer->id,
                    'code' => $balance->customer->code,
                    'name' => $balance->customer->getTranslation('name', $locale, false)
                        ?: ($balance->customer->getTranslation('name', 'en', false) ?: $balance->customer->code),
                ];
            }

            return [
                'id' => $balance->id,
                'customer_id' => $balance->customer_id,
                'customer' => $customer,
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

        $customers = Customer::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->getTranslation('name', $locale, false)
                    ?: ($c->getTranslation('name', 'en', false) ?: $c->code),
            ]);

        return [
            'balances' => $paginator,
            'customers' => $customers,
            'fiscalYears' => FiscalYear::query()->open()->orderBy('year', 'desc')->get(),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->openForPosting()->orderBy('start_date', 'asc')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }
}
