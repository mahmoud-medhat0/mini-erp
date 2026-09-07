<?php

namespace App\Application\Purchasing;

use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierAdjustmentNote;
use App\Models\SupplierBill;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

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
            'supplierAdjustmentNotes' => [],
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
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $supplierId = (string) ($filters['supplier_id'] ?? '');

        $query = SupplierAdjustmentNote::query()->with([
            'supplier',
            'supplierBill',
            'purchaseReturn',
            'lines',
            'journalEntry',
            'payableEntry',
        ])
            ->when($status && in_array($status, SupplierAdjustmentNoteService::ALLOWED_STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId));

        return DataTables::eloquent($query)
            ->filterColumn('number', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('number', 'like', "%{$keyword}%")
                        ->orWhere('ui_label', 'like', "%{$keyword}%")
                        ->orWhere('reason', 'like', "%{$keyword}%")
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($keyword, $needle): void {
                            $supplierQuery->whereRaw('LOWER(CAST(supplier.name AS TEXT)) LIKE ?', [$needle])
                                ->orWhere('code', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('supplierBill', fn (Builder $billQuery) => $billQuery->where('number', 'like', "%{$keyword}%"))
                        ->orWhereHas('purchaseReturn', fn (Builder $returnQuery) => $returnQuery->where('number', 'like', "%{$keyword}%"));
                });
            })
            ->addColumn('supplier_name', fn (SupplierAdjustmentNote $row) => $row->supplier?->code ?? '')
            ->addColumn('source_number', fn (SupplierAdjustmentNote $row) => $row->supplierBill?->number ?? $row->purchaseReturn?->number ?? '')
            ->addColumn('actions', fn () => '')
            ->toJson();
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
