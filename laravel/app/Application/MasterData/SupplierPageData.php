<?php

namespace App\Application\MasterData;

use App\Models\Currency;
use App\Models\Supplier;

class SupplierPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $query = Supplier::query();

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        return [
            'suppliers' => $query->orderBy('code', 'asc')
                ->paginate(15)
                ->withQueryString(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ];
    }
}
