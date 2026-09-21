<?php

namespace App\Application\Taxes;

use App\Models\Currency;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\WithholdingTaxEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class WithholdingTaxPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'entries' => [],
            'taxCodes' => TaxCode::query()->where('tax_type', 'withholding')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'filters' => [
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

        $query = WithholdingTaxEntry::query()
            ->with(['taxCode', 'supplier'])
            ->select('withholding_tax_entry.*')
            ->when($status !== '', fn (Builder $q) => $q->where('withholding_tax_entry.status', $status))
            ->when($supplierId !== '', fn (Builder $q) => $q->where('withholding_tax_entry.supplier_id', $supplierId));

        return DataTables::eloquent($query)
            ->addColumn('supplier', fn () => '')
            ->addColumn('tax_code', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
