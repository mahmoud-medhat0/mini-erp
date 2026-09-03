<?php

namespace App\Application\Accounting;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOpeningBalance;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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

    /**
     * Server-side DataTables feed for the opening balance grid.
     *
     * The customer name is emitted as its raw translations object so the client
     * can render it in the active locale without a round trip.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = CustomerOpeningBalance::query()
            ->join('customer', 'customer.id', '=', 'customer_opening_balance.customer_id')
            ->select([
                'customer_opening_balance.id',
                'customer_opening_balance.customer_id',
                'customer_opening_balance.entry_date',
                'customer_opening_balance.reference',
                'customer_opening_balance.currency',
                'customer_opening_balance.amount_minor',
                'customer_opening_balance.status',
                'customer_opening_balance.posted_at',
                'customer_opening_balance.created_at',
                'customer.code as customer_code',
                'customer.name as customer_name',
            ])
            ->when(
                in_array($status, ['draft', 'posted'], true),
                fn ($q) => $q->where('customer_opening_balance.status', $status),
            )
            ->orderBy('customer_opening_balance.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('customer_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('customer_name', 'customer.code $1')
            // `status`, `id` and `created_at` exist on both joined tables.
            ->orderColumn('status', 'customer_opening_balance.status $1')
            ->orderColumn('id', 'customer_opening_balance.id $1')
            ->editColumn('customer_name', fn ($row) => $this->decodeTranslations($row->customer_name))
            ->toJson();
    }

    /**
     * Spatie stores translations as a JSON column; hand the client the decoded
     * map so it can pick the active locale.
     */
    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
