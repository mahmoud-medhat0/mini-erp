<?php

namespace App\Application\Taxes;

use App\Models\TaxCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TaxCodePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{taxCodes: LengthAwarePaginator, filters: array{search: mixed}}
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
        ];

        $taxCodes = TaxCode::query()
            ->withCount('rates')
            ->when($normalizedFilters['search'], function (Builder $query) use ($normalizedFilters): void {
                $query->where(function (Builder $inner) use ($normalizedFilters): void {
                    $inner->where('code', 'like', "%{$normalizedFilters['search']}%")
                        ->orWhere('name', 'like', "%{$normalizedFilters['search']}%");
                });
            })
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return [
            'taxCodes' => $taxCodes,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return array{taxCode: TaxCode}
     */
    public function editData(string $id): array
    {
        return [
            'taxCode' => TaxCode::query()
                ->with('rates')
                ->findOrFail($id),
        ];
    }
}
