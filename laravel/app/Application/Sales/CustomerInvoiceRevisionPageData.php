<?php

namespace App\Application\Sales;

use App\Models\CustomerInvoiceRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerInvoiceRevisionPageData
{
    /**
     * @param  array{search?: mixed}  $filters
     * @return array{
     *     customerInvoiceRevisions: LengthAwarePaginator,
     *     filters: array{search: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
        ];

        return [
            'customerInvoiceRevisions' => $this->customerInvoiceRevisions($normalizedFilters),
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
    private function customerInvoiceRevisions(array $filters): LengthAwarePaginator
    {
        $query = CustomerInvoiceRevision::query()->with($this->relations());

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('display_string', 'like', "%{$filters['search']}%")
                    ->orWhereHas('customerInvoice', function (Builder $invoiceQuery) use ($filters): void {
                        $invoiceQuery->where('number', 'like', "%{$filters['search']}%");
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
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
