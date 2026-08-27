<?php

namespace App\Application\Catalog;

use App\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductCategoryPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{categories: LengthAwarePaginator, filters: array{search: mixed}}
     */
    public function indexData(array $filters): array
    {
        $search = $filters['search'] ?? null;

        $query = ProductCategory::query();

        if ($search) {
            $query->where(function ($categoryQuery) use ($search): void {
                $categoryQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return [
            'categories' => $query->orderBy('code', 'asc')->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $search,
            ],
        ];
    }
}
