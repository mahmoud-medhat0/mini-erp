<?php

namespace App\Application\Purchasing;

use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierAdjustmentNote;
use App\Models\SupplierBill;
use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SupplierAdjustmentNotePageData
{
    /**
     * @param  array{search?: mixed, status?: mixed, supplier_id?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'supplier_id' => $filters['supplier_id'] ?? null,
        ];

        return [
            'supplierAdjustmentNotes' => $this->supplierAdjustmentNotes($normalizedFilters),
            'activeSuppliers' => $this->activeSuppliers(),
            'postedSupplierBills' => $this->postedSupplierBills(),
            'postedPurchaseReturns' => $this->postedPurchaseReturns(),
            'taxCodes' => $this->activeTaxCodes(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed, supplier_id: mixed}  $filters
     */
    private function supplierAdjustmentNotes(array $filters): LengthAwarePaginator
    {
        $query = SupplierAdjustmentNote::query()->with([
            'supplier',
            'supplierBill',
            'purchaseReturn',
            'lines',
            'journalEntry',
            'payableEntry',
        ]);

        if ($filters['search']) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('number', 'like', "%{$filters['search']}%")
                    ->orWhere('ui_label', 'like', "%{$filters['search']}%")
                    ->orWhere('reason', 'like', "%{$filters['search']}%")
                    ->orWhereHas('supplier', function (Builder $supplierQuery) use ($filters): void {
                        $supplierQuery->where('name', 'like', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['status'] && in_array($filters['status'], SupplierAdjustmentNoteService::ALLOWED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['supplier_id']) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function activeSuppliers(): Collection
    {
        return Supplier::query()->where('status', 'active')->orderBy('name', 'asc')->get();
    }

    /**
     * @return Collection<int, SupplierBill>
     */
    private function postedSupplierBills(): Collection
    {
        return SupplierBill::query()
            ->with(['supplier', 'lines'])
            ->where('status', 'posted')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, PurchaseReturn>
     */
    private function postedPurchaseReturns(): Collection
    {
        return PurchaseReturn::query()
            ->with(['supplier', 'lines'])
            ->where('status', 'posted')
            ->orderBy('number', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, TaxCode>
     */
    private function activeTaxCodes(): Collection
    {
        return TaxCode::query()->where('is_active', true)->orderBy('code', 'asc')->get();
    }
}
