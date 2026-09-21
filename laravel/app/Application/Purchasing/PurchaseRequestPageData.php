<?php

namespace App\Application\Purchasing;

use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class PurchaseRequestPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'requests' => [],
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('status', 'active')->where('is_purchase_enabled', true)->orderBy('code')->get(['id', 'code', 'name', 'unit_of_measure_id']),
            'unitsOfMeasure' => UnitOfMeasure::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => PurchaseRequestService::STATUSES,
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'supplier_id' => (string) ($filters['supplier_id'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $supplierId = (string) ($filters['supplier_id'] ?? '');

        $query = PurchaseRequest::query()
            ->with(['supplier'])
            ->select('purchase_request.*')
            ->when($status !== '' && in_array($status, PurchaseRequestService::STATUSES, true), fn (Builder $q) => $q->where('purchase_request.status', $status))
            ->when($supplierId !== '', fn (Builder $q) => $q->where('purchase_request.supplier_id', $supplierId));

        return DataTables::eloquent($query)
            ->filterColumn('purchase_request.number', function (Builder $query, string $keyword): void {
                $query->where(function (Builder $inner) use ($keyword): void {
                    $inner->where('purchase_request.number', 'like', "%{$keyword}%")
                        ->orWhere('purchase_request.reference', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('supplier', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
