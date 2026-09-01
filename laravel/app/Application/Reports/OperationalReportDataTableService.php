<?php

namespace App\Application\Reports;

use App\Models\CustomerInvoice;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PayableEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReceivableEntry;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockMovementLedger;
use App\Models\SupplierBill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Facades\DataTables;

class OperationalReportDataTableService
{
    /** @var array<string, string> */
    private const SALES_ORDER_SORT_COLUMNS = [
        'order_number' => 'number',
        'order_date' => 'order_date',
        'status' => 'status',
        'currency' => 'currency',
        'ordered_quantity_e6' => 'ordered_quantity_e6',
        'total_minor' => 'total_minor',
    ];

    /** @var array<string, string> */
    private const PURCHASE_ORDER_SORT_COLUMNS = [
        'order_number' => 'number',
        'order_date' => 'order_date',
        'status' => 'status',
        'currency' => 'currency',
        'ordered_quantity_e6' => 'ordered_quantity_e6',
        'total_minor' => 'total_minor',
    ];

    /** @var array<string, string> */
    private const DELIVERY_NOTE_SORT_COLUMNS = [
        'delivery_number' => 'number',
        'delivery_date' => 'delivery_date',
        'status' => 'status',
        'delivered_quantity_e6' => 'delivered_quantity_e6',
    ];

    /** @var array<string, string> */
    private const GOODS_RECEIPT_SORT_COLUMNS = [
        'receipt_number' => 'number',
        'receipt_date' => 'receipt_date',
        'status' => 'status',
        'received_quantity_e6' => 'received_quantity_e6',
    ];

    /** @var array<string, string> */
    private const CUSTOMER_INVOICE_SORT_COLUMNS = [
        'invoice_number' => 'number',
        'invoice_date' => 'invoice_date',
        'due_date' => 'due_date',
        'status' => 'status',
        'total_minor' => 'total_minor',
    ];

    /** @var array<string, string> */
    private const SUPPLIER_BILL_SORT_COLUMNS = [
        'bill_number' => 'number',
        'bill_date' => 'bill_date',
        'due_date' => 'due_date',
        'status' => 'status',
        'total_minor' => 'total_minor',
    ];

    /** @var array<string, string> */
    private const STOCK_MOVEMENT_SORT_COLUMNS = [
        'movement_date' => 'movement_date',
        'movement_type' => 'movement_type',
        'source_type' => 'source_type',
        'quantity_delta_e6' => 'quantity_delta_e6',
        'value_delta_minor' => 'value_delta_minor',
        'balance_quantity_e6' => 'balance_quantity_e6',
    ];

    /** @param array<string, string|null> $filters */
    public function salesOrders(array $filters): JsonResponse
    {
        $query = $this->salesOrderQuery($filters)
            ->with(['customer:id,code,name'])
            ->withCount('lines')
            ->withSum(['lines as ordered_quantity_e6' => function (Builder $query) use ($filters): void {
                $query->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId));
            }], 'quantity_e6');

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchSalesOrders($builder, $search),
            self::SALES_ORDER_SORT_COLUMNS,
        )
            ->addColumn('order_number', fn (SalesOrder $order) => $order->number ?? '—')
            ->addColumn('customer_name', fn (SalesOrder $order) => $order->customer?->name ?? '—')
            ->addColumn('customer_code', fn (SalesOrder $order) => $order->customer?->code ?? '—')
            ->editColumn('order_date', fn (SalesOrder $order) => $order->order_date?->format('Y-m-d'))
            ->editColumn('ordered_quantity_e6', fn (SalesOrder $order) => (int) ($order->ordered_quantity_e6 ?? 0))
            ->editColumn('lines_count', fn (SalesOrder $order) => (int) $order->lines_count)
            ->editColumn('total_minor', fn (SalesOrder $order) => (int) $order->total_minor)
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function salesOrderSummary(array $filters): array
    {
        $query = $this->summaryQuery(
            $this->salesOrderQuery($filters),
            $filters,
            fn (Builder $builder, string $search) => $this->searchSalesOrders($builder, $search),
        );

        return [
            'total_orders_count' => (clone $query)->count(),
            'total_quantity_e6' => (int) SalesOrderLine::query()
                ->whereIn('sales_order_id', (clone $query)->select('sales_order.id'))
                ->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId))
                ->sum('quantity_e6'),
            'total_amount_minor' => (int) (clone $query)->sum('total_minor'),
        ];
    }

    /** @param array<string, string|null> $filters */
    public function purchaseOrders(array $filters): JsonResponse
    {
        $query = $this->purchaseOrderQuery($filters)
            ->with(['supplier:id,code,name'])
            ->withCount('lines')
            ->withSum(['lines as ordered_quantity_e6' => function (Builder $query) use ($filters): void {
                $query->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId));
            }], 'quantity_e6');

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchPurchaseOrders($builder, $search),
            self::PURCHASE_ORDER_SORT_COLUMNS,
        )
            ->addColumn('order_number', fn (PurchaseOrder $order) => $order->number ?? '—')
            ->addColumn('supplier_name', fn (PurchaseOrder $order) => $order->supplier?->name ?? '—')
            ->addColumn('supplier_code', fn (PurchaseOrder $order) => $order->supplier?->code ?? '—')
            ->editColumn('order_date', fn (PurchaseOrder $order) => $order->order_date?->format('Y-m-d'))
            ->editColumn('ordered_quantity_e6', fn (PurchaseOrder $order) => (int) ($order->ordered_quantity_e6 ?? 0))
            ->editColumn('lines_count', fn (PurchaseOrder $order) => (int) $order->lines_count)
            ->editColumn('total_minor', fn (PurchaseOrder $order) => (int) $order->total_minor)
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function purchaseOrderSummary(array $filters): array
    {
        $query = $this->summaryQuery(
            $this->purchaseOrderQuery($filters),
            $filters,
            fn (Builder $builder, string $search) => $this->searchPurchaseOrders($builder, $search),
        );

        return [
            'total_orders_count' => (clone $query)->count(),
            'total_quantity_e6' => (int) PurchaseOrderLine::query()
                ->whereIn('purchase_order_id', (clone $query)->select('purchase_order.id'))
                ->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId))
                ->sum('quantity_e6'),
            'total_amount_minor' => (int) (clone $query)->sum('total_minor'),
        ];
    }

    /** @param array<string, string|null> $filters */
    public function deliveryNotes(array $filters): JsonResponse
    {
        $query = $this->deliveryNoteQuery($filters)
            ->with(['salesOrder.customer:id,code,name', 'salesOrder:id,number,customer_id', 'warehouse:id,code,name'])
            ->withCount('lines')
            ->withSum(['lines as delivered_quantity_e6' => function (Builder $query) use ($filters): void {
                $query->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId));
            }], 'quantity_e6');

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchDeliveryNotes($builder, $search),
            self::DELIVERY_NOTE_SORT_COLUMNS,
        )
            ->addColumn('delivery_number', fn (DeliveryNote $note) => $note->number ?? '—')
            ->addColumn('sales_order_number', fn (DeliveryNote $note) => $note->salesOrder?->number ?? '—')
            ->addColumn('customer_name', fn (DeliveryNote $note) => $note->salesOrder?->customer?->name ?? '—')
            ->addColumn('customer_code', fn (DeliveryNote $note) => $note->salesOrder?->customer?->code ?? '—')
            ->addColumn('warehouse_code', fn (DeliveryNote $note) => $note->warehouse?->code ?? '—')
            ->addColumn('warehouse_name', fn (DeliveryNote $note) => $note->warehouse?->name)
            ->editColumn('delivery_date', fn (DeliveryNote $note) => $note->delivery_date?->format('Y-m-d'))
            ->editColumn('delivered_quantity_e6', fn (DeliveryNote $note) => (int) ($note->delivered_quantity_e6 ?? 0))
            ->editColumn('lines_count', fn (DeliveryNote $note) => (int) $note->lines_count)
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function deliveryNoteSummary(array $filters): array
    {
        $query = $this->summaryQuery(
            $this->deliveryNoteQuery($filters),
            $filters,
            fn (Builder $builder, string $search) => $this->searchDeliveryNotes($builder, $search),
        );

        return [
            'total_notes_count' => (clone $query)->count(),
            'total_delivered_quantity_e6' => (int) DeliveryNoteLine::query()
                ->whereIn('delivery_note_id', (clone $query)->select('delivery_note.id'))
                ->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId))
                ->sum('quantity_e6'),
        ];
    }

    /** @param array<string, string|null> $filters */
    public function goodsReceipts(array $filters): JsonResponse
    {
        $query = $this->goodsReceiptQuery($filters)
            ->with(['purchaseOrder.supplier:id,code,name', 'purchaseOrder:id,number,supplier_id', 'warehouse:id,code,name'])
            ->withCount('lines')
            ->withSum(['lines as received_quantity_e6' => function (Builder $query) use ($filters): void {
                $query->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId));
            }], 'quantity_e6');

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchGoodsReceipts($builder, $search),
            self::GOODS_RECEIPT_SORT_COLUMNS,
        )
            ->addColumn('receipt_number', fn (GoodsReceipt $receipt) => $receipt->number ?? '—')
            ->addColumn('purchase_order_number', fn (GoodsReceipt $receipt) => $receipt->purchaseOrder?->number ?? '—')
            ->addColumn('supplier_name', fn (GoodsReceipt $receipt) => $receipt->purchaseOrder?->supplier?->name ?? '—')
            ->addColumn('supplier_code', fn (GoodsReceipt $receipt) => $receipt->purchaseOrder?->supplier?->code ?? '—')
            ->addColumn('warehouse_code', fn (GoodsReceipt $receipt) => $receipt->warehouse?->code ?? '—')
            ->addColumn('warehouse_name', fn (GoodsReceipt $receipt) => $receipt->warehouse?->name)
            ->editColumn('receipt_date', fn (GoodsReceipt $receipt) => $receipt->receipt_date?->format('Y-m-d'))
            ->editColumn('received_quantity_e6', fn (GoodsReceipt $receipt) => (int) ($receipt->received_quantity_e6 ?? 0))
            ->editColumn('lines_count', fn (GoodsReceipt $receipt) => (int) $receipt->lines_count)
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function goodsReceiptSummary(array $filters): array
    {
        $query = $this->summaryQuery(
            $this->goodsReceiptQuery($filters),
            $filters,
            fn (Builder $builder, string $search) => $this->searchGoodsReceipts($builder, $search),
        );

        return [
            'total_receipts_count' => (clone $query)->count(),
            'total_received_quantity_e6' => (int) GoodsReceiptLine::query()
                ->whereIn('goods_receipt_id', (clone $query)->select('goods_receipt.id'))
                ->when($filters['product_id'], fn (Builder $lineQuery, string $productId) => $lineQuery->where('product_id', $productId))
                ->sum('quantity_e6'),
        ];
    }

    /** @param array<string, string|null> $filters */
    public function customerInvoices(array $filters): JsonResponse
    {
        $query = $this->customerInvoiceQuery($filters)
            ->with(['customer:id,code,name', 'journalEntry:id,number'])
            ->withCount('lines');

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchCustomerInvoices($builder, $search),
            self::CUSTOMER_INVOICE_SORT_COLUMNS,
        )
            ->addColumn('invoice_number', fn (CustomerInvoice $invoice) => $invoice->number ?? '—')
            ->addColumn('customer_name', fn (CustomerInvoice $invoice) => $invoice->customer?->name ?? '—')
            ->addColumn('customer_code', fn (CustomerInvoice $invoice) => $invoice->customer?->code ?? '—')
            ->addColumn('journal_entry_number', fn (CustomerInvoice $invoice) => $invoice->journalEntry?->number)
            ->addColumn('receivable_entry_id', fn (CustomerInvoice $invoice) => $invoice->receivable_entry_id ?? $invoice->resolved_receivable_entry_id)
            ->editColumn('invoice_date', fn (CustomerInvoice $invoice) => $invoice->invoice_date?->format('Y-m-d'))
            ->editColumn('due_date', fn (CustomerInvoice $invoice) => $invoice->due_date?->format('Y-m-d'))
            ->editColumn('lines_count', fn (CustomerInvoice $invoice) => (int) $invoice->lines_count)
            ->editColumn('total_minor', fn (CustomerInvoice $invoice) => (int) $invoice->total_minor)
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function customerInvoiceSummary(array $filters): array
    {
        $query = $this->summaryQuery(
            $this->customerInvoiceQuery($filters),
            $filters,
            fn (Builder $builder, string $search) => $this->searchCustomerInvoices($builder, $search),
        );

        return [
            'total_invoices_count' => (clone $query)->count(),
            'total_amount_minor' => (int) (clone $query)->sum('total_minor'),
        ];
    }

    /** @param array<string, string|null> $filters */
    public function supplierBills(array $filters): JsonResponse
    {
        $query = $this->supplierBillQuery($filters)
            ->with(['supplier:id,code,name', 'journalEntry:id,number'])
            ->withCount('lines');

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchSupplierBills($builder, $search),
            self::SUPPLIER_BILL_SORT_COLUMNS,
        )
            ->addColumn('bill_number', fn (SupplierBill $bill) => $bill->number ?? '—')
            ->addColumn('supplier_name', fn (SupplierBill $bill) => $bill->supplier?->name ?? '—')
            ->addColumn('supplier_code', fn (SupplierBill $bill) => $bill->supplier?->code ?? '—')
            ->addColumn('journal_entry_number', fn (SupplierBill $bill) => $bill->journalEntry?->number)
            ->addColumn('payable_entry_id', fn (SupplierBill $bill) => $bill->payable_entry_id ?? $bill->resolved_payable_entry_id)
            ->editColumn('bill_date', fn (SupplierBill $bill) => $bill->bill_date?->format('Y-m-d'))
            ->editColumn('due_date', fn (SupplierBill $bill) => $bill->due_date?->format('Y-m-d'))
            ->editColumn('lines_count', fn (SupplierBill $bill) => (int) $bill->lines_count)
            ->editColumn('total_minor', fn (SupplierBill $bill) => (int) $bill->total_minor)
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function supplierBillSummary(array $filters): array
    {
        $query = $this->summaryQuery(
            $this->supplierBillQuery($filters),
            $filters,
            fn (Builder $builder, string $search) => $this->searchSupplierBills($builder, $search),
        );

        return [
            'total_bills_count' => (clone $query)->count(),
            'total_amount_minor' => (int) (clone $query)->sum('total_minor'),
        ];
    }

    /** @param array<string, string|null> $filters */
    public function stockMovements(array $filters): JsonResponse
    {
        $query = $this->stockMovementQuery($filters)
            ->with(['warehouse.branch:id,code,name', 'warehouse:id,branch_id,code,name', 'product:id,code,name', 'unitOfMeasure:id,code', 'journalEntry:id,number']);

        return $this->dataTable(
            $query,
            fn (Builder $builder, string $search) => $this->searchStockMovements($builder, $search),
            self::STOCK_MOVEMENT_SORT_COLUMNS,
        )
            ->addColumn('warehouse_code', fn (StockMovementLedger $movement) => $movement->warehouse?->code)
            ->addColumn('warehouse_name', fn (StockMovementLedger $movement) => $movement->warehouse?->name)
            ->addColumn('branch_id', fn (StockMovementLedger $movement) => $movement->warehouse?->branch_id)
            ->addColumn('branch_code', fn (StockMovementLedger $movement) => $movement->warehouse?->branch?->code)
            ->addColumn('branch_name', fn (StockMovementLedger $movement) => $movement->warehouse?->branch?->name)
            ->addColumn('product_name', fn (StockMovementLedger $movement) => $movement->product?->name ?? '—')
            ->addColumn('product_code', fn (StockMovementLedger $movement) => $movement->product?->code ?? '—')
            ->addColumn('uom_code', fn (StockMovementLedger $movement) => $movement->unitOfMeasure?->code ?? '—')
            ->addColumn('journal_entry_number', fn (StockMovementLedger $movement) => $movement->journalEntry?->number)
            ->editColumn('movement_date', fn (StockMovementLedger $movement) => $movement->movement_date?->format('Y-m-d'))
            ->editColumn('quantity_delta_e6', fn (StockMovementLedger $movement) => (int) $movement->quantity_delta_e6)
            ->editColumn('value_delta_minor', fn (StockMovementLedger $movement) => (int) $movement->value_delta_minor)
            ->editColumn('unit_cost_e6', fn (StockMovementLedger $movement) => (int) $movement->unit_cost_e6)
            ->editColumn('balance_quantity_e6', fn (StockMovementLedger $movement) => (int) $movement->balance_quantity_e6)
            ->editColumn('balance_valuation_amount_minor', fn (StockMovementLedger $movement) => (int) $movement->balance_valuation_amount_minor)
            ->toJson();
    }

    /** @param array<string, string|null> $filters */
    public function stockMovementSummary(array $filters): array
    {
        $query = $this->summaryQuery(
            $this->stockMovementQuery($filters),
            $filters,
            fn (Builder $builder, string $search) => $this->searchStockMovements($builder, $search),
        );

        return [
            'total_movements_count' => (clone $query)->count(),
            'total_quantity_delta_e6' => (int) (clone $query)->sum('quantity_delta_e6'),
            'total_value_delta_minor' => (int) (clone $query)->sum('value_delta_minor'),
        ];
    }

    /** @param array<string, string> $sortColumns */
    private function dataTable(Builder $query, callable $search, array $sortColumns): EloquentDataTable
    {
        return DataTables::eloquent($query)
            ->filter(function (Builder $builder) use ($search): void {
                $keyword = trim((string) request()->input('search.value', ''));

                if ($keyword !== '') {
                    $search($builder, $keyword);
                }
            })
            ->order(function (Builder $builder) use ($sortColumns): void {
                foreach ((array) request()->input('order', []) as $order) {
                    if (! is_array($order)) {
                        continue;
                    }

                    $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
                    $data = $index === false ? null : request()->input("columns.$index.data");

                    if (! is_string($data) || ! isset($sortColumns[$data])) {
                        continue;
                    }

                    $direction = ($order['dir'] ?? null) === 'desc' ? 'desc' : 'asc';
                    $builder->orderBy($sortColumns[$data], $direction);
                }

                $builder->orderBy($builder->qualifyColumn('id'));
            });
    }

    /** @param array<string, string|null> $filters */
    private function summaryQuery(Builder $query, array $filters, callable $search): Builder
    {
        $keyword = trim((string) ($filters['search'] ?? ''));

        if ($keyword !== '') {
            $search($query, $keyword);
        }

        return $query;
    }

    /** @param array<string, string|null> $filters */
    private function salesOrderQuery(array $filters): Builder
    {
        return SalesOrder::query()
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('order_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('order_date', '<=', $date))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['customer_id'], fn (Builder $query, string $id) => $query->where('customer_id', $id))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['product_id'], fn (Builder $query, string $id) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('product_id', $id)));
    }

    private function searchSalesOrders(Builder $query, string $search): void
    {
        $pattern = $this->containsPattern($search);

        $query->where(fn (Builder $nested) => $nested
            ->whereLike('number', $pattern)
            ->orWhereLike('order_date', $pattern)
            ->orWhereLike('status', $pattern)
            ->orWhereLike('currency', $pattern)
            ->orWhereHas('customer', fn (Builder $customer) => $this->searchNamedEntity($customer, $pattern)));
    }

    /** @param array<string, string|null> $filters */
    private function purchaseOrderQuery(array $filters): Builder
    {
        return PurchaseOrder::query()
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('order_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('order_date', '<=', $date))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier_id'], fn (Builder $query, string $id) => $query->where('supplier_id', $id))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['product_id'], fn (Builder $query, string $id) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('product_id', $id)));
    }

    private function searchPurchaseOrders(Builder $query, string $search): void
    {
        $pattern = $this->containsPattern($search);

        $query->where(fn (Builder $nested) => $nested
            ->whereLike('number', $pattern)
            ->orWhereLike('order_date', $pattern)
            ->orWhereLike('status', $pattern)
            ->orWhereLike('currency', $pattern)
            ->orWhereHas('supplier', fn (Builder $supplier) => $this->searchNamedEntity($supplier, $pattern)));
    }

    /** @param array<string, string|null> $filters */
    private function deliveryNoteQuery(array $filters): Builder
    {
        return DeliveryNote::query()
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('delivery_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('delivery_date', '<=', $date))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['customer_id'], fn (Builder $query, string $id) => $query->whereHas('salesOrder', fn (Builder $order) => $order->where('customer_id', $id)))
            ->when($filters['warehouse_id'], fn (Builder $query, string $id) => $query->where('warehouse_id', $id))
            ->when($filters['product_id'], fn (Builder $query, string $id) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('product_id', $id)));
    }

    private function searchDeliveryNotes(Builder $query, string $search): void
    {
        $pattern = $this->containsPattern($search);

        $query->where(fn (Builder $nested) => $nested
            ->whereLike('number', $pattern)
            ->orWhereLike('delivery_date', $pattern)
            ->orWhereLike('status', $pattern)
            ->orWhereHas('warehouse', fn (Builder $warehouse) => $this->searchNamedEntity($warehouse, $pattern))
            ->orWhereHas('salesOrder', fn (Builder $order) => $order
                ->whereLike('number', $pattern)
                ->orWhereHas('customer', fn (Builder $customer) => $this->searchNamedEntity($customer, $pattern))));
    }

    /** @param array<string, string|null> $filters */
    private function goodsReceiptQuery(array $filters): Builder
    {
        return GoodsReceipt::query()
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('receipt_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('receipt_date', '<=', $date))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier_id'], fn (Builder $query, string $id) => $query->whereHas('purchaseOrder', fn (Builder $order) => $order->where('supplier_id', $id)))
            ->when($filters['warehouse_id'], fn (Builder $query, string $id) => $query->where('warehouse_id', $id))
            ->when($filters['product_id'], fn (Builder $query, string $id) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('product_id', $id)));
    }

    private function searchGoodsReceipts(Builder $query, string $search): void
    {
        $pattern = $this->containsPattern($search);

        $query->where(fn (Builder $nested) => $nested
            ->whereLike('number', $pattern)
            ->orWhereLike('receipt_date', $pattern)
            ->orWhereLike('status', $pattern)
            ->orWhereHas('warehouse', fn (Builder $warehouse) => $this->searchNamedEntity($warehouse, $pattern))
            ->orWhereHas('purchaseOrder', fn (Builder $order) => $order
                ->whereLike('number', $pattern)
                ->orWhereHas('supplier', fn (Builder $supplier) => $this->searchNamedEntity($supplier, $pattern))));
    }

    /** @param array<string, string|null> $filters */
    private function customerInvoiceQuery(array $filters): Builder
    {
        return CustomerInvoice::query()
            ->select('customer_invoice.*')
            ->addSelect([
                'resolved_receivable_entry_id' => ReceivableEntry::query()
                    ->select('id')
                    ->where('source_type', 'customer_invoice')
                    ->whereColumn('source_id', 'customer_invoice.id')
                    ->limit(1),
            ])
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('invoice_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('invoice_date', '<=', $date))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['customer_id'], fn (Builder $query, string $id) => $query->where('customer_id', $id))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['product_id'], fn (Builder $query, string $id) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('product_id', $id)));
    }

    private function searchCustomerInvoices(Builder $query, string $search): void
    {
        $pattern = $this->containsPattern($search);

        $query->where(fn (Builder $nested) => $nested
            ->whereLike('number', $pattern)
            ->orWhereLike('reference', $pattern)
            ->orWhereLike('invoice_date', $pattern)
            ->orWhereLike('due_date', $pattern)
            ->orWhereLike('status', $pattern)
            ->orWhereLike('currency', $pattern)
            ->orWhereHas('customer', fn (Builder $customer) => $this->searchNamedEntity($customer, $pattern)));
    }

    /** @param array<string, string|null> $filters */
    private function supplierBillQuery(array $filters): Builder
    {
        return SupplierBill::query()
            ->select('supplier_bill.*')
            ->addSelect([
                'resolved_payable_entry_id' => PayableEntry::query()
                    ->select('id')
                    ->where('source_type', 'supplier_bill')
                    ->whereColumn('source_id', 'supplier_bill.id')
                    ->limit(1),
            ])
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('bill_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('bill_date', '<=', $date))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier_id'], fn (Builder $query, string $id) => $query->where('supplier_id', $id))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['product_id'], fn (Builder $query, string $id) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('product_id', $id)));
    }

    private function searchSupplierBills(Builder $query, string $search): void
    {
        $pattern = $this->containsPattern($search);

        $query->where(fn (Builder $nested) => $nested
            ->whereLike('number', $pattern)
            ->orWhereLike('reference', $pattern)
            ->orWhereLike('supplier_reference', $pattern)
            ->orWhereLike('bill_date', $pattern)
            ->orWhereLike('due_date', $pattern)
            ->orWhereLike('status', $pattern)
            ->orWhereLike('currency', $pattern)
            ->orWhereHas('supplier', fn (Builder $supplier) => $this->searchNamedEntity($supplier, $pattern)));
    }

    /** @param array<string, string|null> $filters */
    private function stockMovementQuery(array $filters): Builder
    {
        return StockMovementLedger::query()
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->where('movement_date', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->where('movement_date', '<=', $date))
            ->when($filters['movement_type'], fn (Builder $query, string $type) => $query->where('movement_type', $type))
            ->when($filters['product_id'], fn (Builder $query, string $id) => $query->where('product_id', $id))
            ->when($filters['warehouse_id'], fn (Builder $query, string $id) => $query->where('warehouse_id', $id))
            ->when($filters['currency'], fn (Builder $query, string $currency) => $query->where('currency', $currency));
    }

    private function searchStockMovements(Builder $query, string $search): void
    {
        $pattern = $this->containsPattern($search);

        $query->where(fn (Builder $nested) => $nested
            ->whereLike('movement_date', $pattern)
            ->orWhereLike('source_type', $pattern)
            ->orWhereLike('source_id', $pattern)
            ->orWhereLike('movement_type', $pattern)
            ->orWhereHas('warehouse', function (Builder $warehouse) use ($pattern): void {
                $this->searchNamedEntity($warehouse, $pattern);
                $warehouse->orWhereHas('branch', fn (Builder $branch) => $this->searchNamedEntity($branch, $pattern));
            })
            ->orWhereHas('product', fn (Builder $product) => $this->searchNamedEntity($product, $pattern)));
    }

    private function searchNamedEntity(Builder $query, string $pattern): void
    {
        $query->whereLike('code', $pattern)
            ->orWhereLike('name->en', $pattern)
            ->orWhereLike('name->ar', $pattern);
    }

    private function containsPattern(string $search): string
    {
        return "%{$search}%";
    }
}
