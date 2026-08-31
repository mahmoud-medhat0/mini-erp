<?php

namespace App\Application\Catalog;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ProductPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     products: LengthAwarePaginator,
     *     uoms: EloquentCollection<int, UnitOfMeasure>,
     *     categories: EloquentCollection<int, ProductCategory>,
     *     filters: array{search: mixed, type: mixed, status: mixed, product_category_id: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = $filters['search'] ?? null;
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $categoryId = $filters['product_category_id'] ?? null;

        $query = Product::query()->with(['unitOfMeasure', 'category']);

        if ($search) {
            $query->where(function ($productQuery) use ($search): void {
                $productQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($type && in_array($type, ProductService::ALLOWED_TYPES, true)) {
            $query->where('type', $type);
        }

        if ($status && in_array($status, ProductService::ALLOWED_STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($categoryId) {
            $query->where('product_category_id', $categoryId);
        }

        return [
            'products' => $query->orderBy('code', 'asc')->paginate(15)->withQueryString(),
            'uoms' => UnitOfMeasure::query()->where('is_active', true)->orderBy('code', 'asc')->get(),
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('code', 'asc')->get(),
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'product_category_id' => $categoryId,
            ],
        ];
    }
}
