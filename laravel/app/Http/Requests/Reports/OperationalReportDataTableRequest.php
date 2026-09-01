<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OperationalReportDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $columnSchema = $this->columnSchema();

        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', Rule::in($this->allowedStatuses())],
            'customer_id' => ['bail', 'nullable', 'uuid', 'exists:customer,id'],
            'supplier_id' => ['bail', 'nullable', 'uuid', 'exists:supplier,id'],
            'product_id' => ['bail', 'nullable', 'uuid', 'exists:product,id'],
            'warehouse_id' => ['bail', 'nullable', 'uuid', 'exists:warehouse,id'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'movement_type' => ['nullable', 'string', Rule::in([
                'receipt',
                'issue',
                'reversal',
                'scrap',
                'transfer_out',
                'transfer_in',
                'adjustment',
                'landed_cost',
            ])],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'in:10,25,50,100'],
            'search.value' => ['nullable', 'string', 'max:150'],
            'search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
            'columns' => ['nullable', 'array', 'max:'.count($columnSchema)],
            'columns.*.data' => ['required_with:columns', 'string', Rule::in(array_keys($columnSchema))],
            'columns.*.name' => ['required_with:columns', 'string', Rule::in(array_values(array_unique(array_column($columnSchema, 'name'))))],
            'columns.*.searchable' => ['required_with:columns', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.orderable' => ['required_with:columns', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'columns.*.search.value' => ['nullable', 'string', 'max:120'],
            'columns.*.search.regex' => ['nullable', Rule::in([false, 0, '0', 'false'])],
            'order' => ['nullable', 'array', 'max:3'],
            'order.*.column' => ['required_with:order', 'integer', 'min:0'],
            'order.*.dir' => ['required_with:order', Rule::in(['asc', 'desc'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $schema = $this->columnSchema();
            $columns = $this->input('columns', []);

            if (! is_array($columns)) {
                return;
            }

            foreach ($columns as $index => $column) {
                if (! is_array($column) || ! isset($schema[$column['data'] ?? ''])) {
                    continue;
                }

                $definition = $schema[$column['data']];

                if (($column['name'] ?? null) !== $definition['name']) {
                    $validator->errors()->add("columns.$index.name", 'The column name does not match the requested report column.');
                }

                foreach (['searchable', 'orderable'] as $capability) {
                    if ($this->booleanValue($column[$capability] ?? null) !== $definition[$capability]) {
                        $validator->errors()->add("columns.$index.$capability", "The column $capability capability does not match the report schema.");
                    }
                }
            }

            foreach ((array) $this->input('order', []) as $index => $order) {
                $columnIndex = is_array($order) ? filter_var($order['column'] ?? null, FILTER_VALIDATE_INT) : false;

                if ($columnIndex === false || ! array_key_exists($columnIndex, $columns)) {
                    $validator->errors()->add("order.$index.column", 'The order column must reference a submitted column.');

                    continue;
                }

                $data = is_array($columns[$columnIndex]) ? ($columns[$columnIndex]['data'] ?? '') : '';

                if (! isset($schema[$data]) || ! $schema[$data]['orderable']) {
                    $validator->errors()->add("order.$index.column", 'The selected report column is not orderable.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->query('currency');

        if (is_string($currency)) {
            $this->merge(['currency' => strtoupper(trim($currency))]);
        }
    }

    /**
     * @return array<string, string|null>
     */
    public function reportFilters(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'status' => $validated['status'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'currency' => isset($validated['currency']) ? strtoupper($validated['currency']) : null,
            'movement_type' => $validated['movement_type'] ?? null,
        ];
    }

    /** @return list<string> */
    private function allowedStatuses(): array
    {
        $routeName = (string) $this->route()?->getName();

        return match (true) {
            in_array($routeName, ['reports.sales-orders.data', 'reports.purchase-orders.data'], true) => [
                'draft', 'submitted', 'confirmed', 'cancelled',
            ],
            in_array($routeName, ['reports.customer-invoices.data', 'reports.supplier-bills.data'], true) => [
                'draft', 'submitted', 'approved', 'posted', 'cancelled',
            ],
            in_array($routeName, ['reports.delivery-notes.data', 'reports.goods-receipts.data'], true) => [
                'draft', 'confirmed', 'cancelled',
            ],
            default => ['draft', 'submitted', 'approved', 'confirmed', 'posted', 'cancelled'],
        };
    }

    /**
     * @return array<string, array{name: string, searchable: bool, orderable: bool}>
     */
    private function columnSchema(): array
    {
        $routeName = (string) $this->route()?->getName();

        return match ($routeName) {
            'reports.sales-orders.data' => [
                'order_number' => $this->column('number'),
                'customer_name' => $this->column('customer_name', orderable: false),
                'order_date' => $this->column('order_date'),
                'status' => $this->column('status'),
                'currency' => $this->column('currency'),
                'ordered_quantity_e6' => $this->column('ordered_quantity_e6', searchable: false),
                'total_minor' => $this->column('total_minor', searchable: false),
            ],
            'reports.purchase-orders.data' => [
                'order_number' => $this->column('number'),
                'supplier_name' => $this->column('supplier_name', orderable: false),
                'order_date' => $this->column('order_date'),
                'status' => $this->column('status'),
                'currency' => $this->column('currency'),
                'ordered_quantity_e6' => $this->column('ordered_quantity_e6', searchable: false),
                'total_minor' => $this->column('total_minor', searchable: false),
            ],
            'reports.delivery-notes.data' => [
                'delivery_number' => $this->column('number'),
                'sales_order_number' => $this->column('sales_order_number', orderable: false),
                'customer_name' => $this->column('customer_name', orderable: false),
                'warehouse_name' => $this->column('warehouse_name', orderable: false),
                'delivery_date' => $this->column('delivery_date'),
                'status' => $this->column('status'),
                'delivered_quantity_e6' => $this->column('delivered_quantity_e6', searchable: false),
            ],
            'reports.goods-receipts.data' => [
                'receipt_number' => $this->column('number'),
                'purchase_order_number' => $this->column('purchase_order_number', orderable: false),
                'supplier_name' => $this->column('supplier_name', orderable: false),
                'warehouse_name' => $this->column('warehouse_name', orderable: false),
                'receipt_date' => $this->column('receipt_date'),
                'status' => $this->column('status'),
                'received_quantity_e6' => $this->column('received_quantity_e6', searchable: false),
            ],
            'reports.customer-invoices.data' => [
                'invoice_number' => $this->column('number'),
                'customer_name' => $this->column('customer_name', orderable: false),
                'invoice_date' => $this->column('invoice_date'),
                'due_date' => $this->column('due_date'),
                'status' => $this->column('status'),
                'total_minor' => $this->column('total_minor', searchable: false),
                'journal_entry_number' => $this->column('journal_entry_number', searchable: false, orderable: false),
                'receivable_entry_id' => $this->column('receivable_entry_id', searchable: false, orderable: false),
            ],
            'reports.supplier-bills.data' => [
                'bill_number' => $this->column('number'),
                'supplier_name' => $this->column('supplier_name', orderable: false),
                'bill_date' => $this->column('bill_date'),
                'due_date' => $this->column('due_date'),
                'status' => $this->column('status'),
                'total_minor' => $this->column('total_minor', searchable: false),
                'journal_entry_number' => $this->column('journal_entry_number', searchable: false, orderable: false),
                'payable_entry_id' => $this->column('payable_entry_id', searchable: false, orderable: false),
            ],
            'reports.stock-movements.data' => [
                'movement_date' => $this->column('movement_date'),
                'movement_type' => $this->column('movement_type'),
                'warehouse_name' => $this->column('warehouse_name', orderable: false),
                'source_type' => $this->column('source_type'),
                'product_name' => $this->column('product_name', orderable: false),
                'quantity_delta_e6' => $this->column('quantity_delta_e6', searchable: false),
                'value_delta_minor' => $this->column('value_delta_minor', searchable: false),
                'balance_quantity_e6' => $this->column('balance_quantity_e6', searchable: false),
                'journal_entry_number' => $this->column('journal_entry_number', searchable: false, orderable: false),
            ],
            default => [],
        };
    }

    /** @return array{name: string, searchable: bool, orderable: bool} */
    private function column(string $name, bool $searchable = true, bool $orderable = true): array
    {
        return compact('name', 'searchable', 'orderable');
    }

    private function booleanValue(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => null,
        };
    }
}
