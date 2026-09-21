<?php

namespace App\Application\Sales;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class SalesQuotationPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'quotations' => [],
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('status', 'active')->where('is_sales_enabled', true)->orderBy('code')->get(['id', 'code', 'name', 'unit_of_measure_id']),
            'unitsOfMeasure' => UnitOfMeasure::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => SalesQuotationService::STATUSES,
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'customer_id' => (string) ($filters['customer_id'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $customerId = (string) ($filters['customer_id'] ?? '');

        $query = SalesQuotation::query()
            ->with(['customer'])
            ->select('sales_quotation.*')
            ->when($status !== '' && in_array($status, SalesQuotationService::STATUSES, true), fn (Builder $q) => $q->where('sales_quotation.status', $status))
            ->when($customerId !== '', fn (Builder $q) => $q->where('sales_quotation.customer_id', $customerId));

        return DataTables::eloquent($query)
            ->filterColumn('sales_quotation.number', function (Builder $query, string $keyword): void {
                $query->where(function (Builder $inner) use ($keyword): void {
                    $inner->where('sales_quotation.number', 'like', "%{$keyword}%")
                        ->orWhere('sales_quotation.reference', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('customer', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
