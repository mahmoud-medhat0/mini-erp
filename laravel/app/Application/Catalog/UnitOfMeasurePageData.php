<?php

namespace App\Application\Catalog;

use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UnitOfMeasurePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{uoms: LengthAwarePaginator, filters: array{search: mixed}}
     */
    public function indexData(array $filters): array
    {
        $search = $filters['search'] ?? null;

        $query = UnitOfMeasure::query();

        if ($search) {
            $query->where(function ($uomQuery) use ($search): void {
                $uomQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return [
            'uoms' => $query->orderBy('code', 'asc')->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $search,
            ],
        ];
    }
}
