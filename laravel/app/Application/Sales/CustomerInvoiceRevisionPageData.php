<?php

namespace App\Application\Sales;

use App\Models\CustomerInvoiceRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class CustomerInvoiceRevisionPageData
{
    /**
     * @param  array{search?: mixed}  $filters
     * @return array{
     *     customerInvoiceRevisions: array,
     *     filters: array{search: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
        ];

        return [
            'customerInvoiceRevisions' => [],
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return array{revision: CustomerInvoiceRevision, snapshot: mixed}
     */
    public function showData(string $id): array
    {
        $revision = CustomerInvoiceRevision::query()
            ->with($this->relations())
            ->where('id', $id)
            ->firstOrFail();

        return [
            'revision' => $revision,
            'snapshot' => json_decode((string) $revision->snapshot_json, true),
        ];
    }

    /**
     * @param  array{search: mixed}  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $query = CustomerInvoiceRevision::query()
            ->with($this->relations())
            ->leftJoin('customer_invoice as revision_invoice', 'revision_invoice.id', '=', 'customer_invoice_revision.customer_invoice_id')
            ->leftJoin('customer as revision_customer', 'revision_customer.id', '=', 'revision_invoice.customer_id')
            ->select('customer_invoice_revision.*');

        return DataTables::eloquent($query)
            ->filterColumn('customer_invoice_revision.display_string', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('customer_invoice_revision.display_string', 'like', "%{$keyword}%")
                        ->orWhere('revision_invoice.number', 'like', "%{$keyword}%")
                        ->orWhere('revision_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(revision_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->filterColumn('invoice_number', fn (Builder $query, string $keyword) => $query->where('revision_invoice.number', 'like', "%{$keyword}%"))
            ->filterColumn('customer_name', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('revision_customer.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(revision_customer.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->orderColumn('invoice_number', 'revision_invoice.number $1')
            ->orderColumn('customer_name', 'revision_customer.code $1')
            ->addColumn('invoice_number', fn (CustomerInvoiceRevision $row) => $row->customerInvoice?->number ?? '')
            ->addColumn('customer_name', fn (CustomerInvoiceRevision $row) => $row->customerInvoice?->customer?->name ?? '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'customerInvoice.customer',
            'customerCreditNote',
            'salesReturn',
            'createdBy',
            'lines.product',
            'lines.unitOfMeasure',
        ];
    }
}
