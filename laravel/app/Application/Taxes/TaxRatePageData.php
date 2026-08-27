<?php

namespace App\Application\Taxes;

use App\Models\TaxCode;
use App\Models\TaxRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class TaxRatePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{taxRates: LengthAwarePaginator, taxCodes: EloquentCollection<int, TaxCode>, filters: array{tax_code_id: mixed}}
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'tax_code_id' => $filters['tax_code_id'] ?? null,
        ];

        return [
            'taxRates' => TaxRate::query()
                ->with('taxCode')
                ->when($normalizedFilters['tax_code_id'], fn (Builder $query) => $query->where('tax_code_id', $normalizedFilters['tax_code_id']))
                ->orderBy('effective_from', 'desc')
                ->paginate(20)
                ->withQueryString(),
            'taxCodes' => TaxCode::query()->orderBy('code')->get(),
            'filters' => $normalizedFilters,
        ];
    }
}
