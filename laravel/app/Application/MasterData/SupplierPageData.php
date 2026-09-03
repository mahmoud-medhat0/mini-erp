<?php

namespace App\Application\MasterData;

use App\Models\Currency;
use App\Models\Supplier;

use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

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

    /**
     * Server-side DataTables feed for supplier grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');

        $query = Supplier::query()
            ->select(['id', 'code', 'name', 'email', 'phone', 'address', 'tax_number', 'status', 'lock_version', 'created_at'])
            ->when($status && in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->orderBy('code', 'asc');

        return DataTables::eloquent($query)
            ->filterColumn('name', function ($q, $keyword): void {
                $q->where(function ($inner) use ($keyword): void {
                    $inner->where('code', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', ['%'.mb_strtolower($keyword).'%'])
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%")
                        ->orWhere('tax_number', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('name', fn ($row) => $this->translatableName($row->name))
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
