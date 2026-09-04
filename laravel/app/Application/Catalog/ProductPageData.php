<?php

namespace App\Application\Catalog;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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

        return [
            'products' => [],
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

    /**
     * Server-side DataTables feed for products grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $type = (string) ($filters['type'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $categoryId = (string) ($filters['product_category_id'] ?? '');

        $query = Product::query()
            ->with(['unitOfMeasure', 'category'])
            ->leftJoin('unit_of_measure', 'unit_of_measure.id', '=', 'product.unit_of_measure_id')
            ->leftJoin('product_category', 'product_category.id', '=', 'product.product_category_id')
            ->select('product.*')
            ->when($type && in_array($type, ProductService::ALLOWED_TYPES, true), fn ($q) => $q->where('product.type', $type))
            ->when($status && in_array($status, ProductService::ALLOWED_STATUSES, true), fn ($q) => $q->where('product.status', $status))
            ->when($categoryId, fn ($q) => $q->where('product.product_category_id', $categoryId))
            ->orderBy('product.code', 'asc');

        return DataTables::eloquent($query)
            ->filterColumn('name', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($keyword, $needle): void {
                    $inner->where('product.code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(product.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(product.description AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->editColumn('name', fn ($row) => $this->translatableName($row->name))
            ->addColumn('uom_name', fn ($row) => $row->unitOfMeasure?->name ? $this->translatableName($row->unitOfMeasure->name) : null)
            ->addColumn('category_name', fn ($row) => $row->category?->name ? $this->translatableName($row->category->name) : null)
            ->editColumn('status', fn ($row) => $row->status)
            ->addColumn('actions', fn ($row) => '')
            ->rawColumns(['actions'])
            ->toJson();
    }

    private function translatableName(mixed $name): array|string
    {
        if (! is_string($name)) {
            return is_array($name) ? $name : (string) $name;
        }

        $decoded = json_decode($name, true);

        return is_array($decoded) ? $decoded : $name;
    }
}
