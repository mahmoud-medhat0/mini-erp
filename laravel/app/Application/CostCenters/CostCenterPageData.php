<?php

namespace App\Application\CostCenters;

use App\Models\CostCenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CostCenterPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     costCenters: LengthAwarePaginator,
     *     filters: array{search: string, category: string, status: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $costCenters = CostCenter::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', function ($query) use ($category): void {
                $query->where('category', $category);
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return [
            'costCenters' => $costCenters,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
            ],
        ];
    }
}
