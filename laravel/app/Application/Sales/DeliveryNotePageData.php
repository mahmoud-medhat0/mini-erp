<?php

namespace App\Application\Sales;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class DeliveryNotePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, warehouse_id?: mixed}  $filters
     * @return array{
     *     deliveryNotes: array,
     *     confirmedSalesOrders: Collection<int, SalesOrder>,
     *     warehouses: Collection<int, Warehouse>,
     *     filters: array{search: mixed, status: mixed, warehouse_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'warehouse_id' => $filters['warehouse_id'] ?? null,
        ];

        return [
            'deliveryNotes' => [],
            'confirmedSalesOrders' => $this->confirmedSalesOrders(),
            'warehouses' => $this->activeWarehouses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, warehouse_id: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $warehouseId = (string) ($filters['warehouse_id'] ?? '');

        $query = DeliveryNote::query()
            ->with(['salesOrder.customer', 'warehouse', 'lines.product', 'lines.unitOfMeasure'])
            ->leftJoin('sales_order as delivery_sales_order', 'delivery_sales_order.id', '=', 'delivery_note.sales_order_id')
            ->leftJoin('customer as delivery_customer', 'delivery_customer.id', '=', 'delivery_sales_order.customer_id')
            ->leftJoin('warehouse as delivery_warehouse', 'delivery_warehouse.id', '=', 'delivery_note.warehouse_id')
            ->select('delivery_note.*')
            ->when($status && in_array($status, DeliveryNoteService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('delivery_note.status', $status))
            ->when($warehouseId, fn (Builder $query) => $query->where('delivery_note.warehouse_id', $warehouseId));

        return DataTables::eloquent($query)
            ->filterColumn('delivery_note.number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('delivery_note.number', 'like', "%{$keyword}%")
                        ->orWhere('delivery_note.reference', 'like', "%{$keyword}%")
                        ->orWhere('delivery_note.notes', 'like', "%{$keyword}%")
                        ->orWhere('delivery_sales_order.number', 'like', "%{$keyword}%")
                        ->orWhere('delivery_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(delivery_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('sales_order_number', fn (Builder $query, string $keyword) => $query->where('delivery_sales_order.number', 'like', "%{$keyword}%"))
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('delivery_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(delivery_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('warehouse_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('delivery_warehouse.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(delivery_warehouse.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('sales_order_number', 'delivery_sales_order.number $1')
            ->orderColumn('customer_name', 'delivery_customer.code $1')
            ->orderColumn('warehouse_name', 'delivery_warehouse.code $1')
            ->addColumn('sales_order_number', fn (DeliveryNote $row) => $row->salesOrder?->number ?? '')
            ->addColumn('customer_name', fn (DeliveryNote $row) => $row->salesOrder?->customer?->name ?? '')
            ->addColumn('warehouse_name', fn (DeliveryNote $row) => $row->warehouse?->name ?? '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * @return Collection<int, SalesOrder>
     */
    private function confirmedSalesOrders(): Collection
    {
        return SalesOrder::query()
            ->with(['customer', 'lines.product', 'lines.unitOfMeasure'])
            ->where('status', 'confirmed')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function activeWarehouses(): Collection
    {
        return Warehouse::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('code', 'asc')
            ->get(['id', 'code', 'name', 'is_default']);
    }
}
