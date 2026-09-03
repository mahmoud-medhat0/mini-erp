<?php

namespace App\Application\Accounting;

use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Supplier;
use App\Models\SupplierOpeningBalance;

use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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

    /**
     * Server-side DataTables feed for the supplier opening balance grid.
     *
     * The supplier name is emitted as its raw translations object so the client
     * can render it in the active locale without a round trip.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = SupplierOpeningBalance::query()
            ->join('supplier', 'supplier.id', '=', 'supplier_opening_balance.supplier_id')
            ->select([
                'supplier_opening_balance.id',
                'supplier_opening_balance.supplier_id',
                'supplier_opening_balance.entry_date',
                'supplier_opening_balance.reference',
                'supplier_opening_balance.currency',
                'supplier_opening_balance.amount_minor',
                'supplier_opening_balance.status',
                'supplier_opening_balance.posted_at',
                'supplier_opening_balance.created_at',
                'supplier.code as supplier_code',
                'supplier.name as supplier_name',
            ])
            ->when(
                in_array($status, ['draft', 'posted'], true),
                fn ($q) => $q->where('supplier_opening_balance.status', $status),
            )
            ->orderBy('supplier_opening_balance.created_at', 'desc');

        return DataTables::eloquent($query)
            ->filterColumn('supplier_name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('supplier.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('supplier_name', 'supplier.code $1')
            ->orderColumn('status', 'supplier_opening_balance.status $1')
            ->orderColumn('id', 'supplier_opening_balance.id $1')
            ->editColumn('supplier_name', fn ($row) => $this->decodeTranslations($row->supplier_name))
            ->toJson();
    }

    private function decodeTranslations(mixed $value): array|string
    {
        if (! is_string($value)) {
            return is_array($value) ? $value : (string) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }
}
