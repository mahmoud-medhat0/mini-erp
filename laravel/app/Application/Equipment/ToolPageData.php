<?php

namespace App\Application\Equipment;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ToolPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'items' => [],
            'categories' => ToolCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'employees' => Employee::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'statuses' => ToolService::STATUSES,
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'tool_category_id' => (string) ($filters['tool_category_id'] ?? ''),
                'branch_id' => (string) ($filters['branch_id'] ?? ''),
            ],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function datatable(array $filters = []): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $categoryId = (string) ($filters['tool_category_id'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');

        $query = Tool::query()
            ->with(['category', 'branch', 'custodian'])
            ->select('tool.*')
            ->when($status !== '' && in_array($status, ToolService::STATUSES, true), fn (Builder $q) => $q->where('tool.status', $status))
            ->when($categoryId !== '', fn (Builder $q) => $q->where('tool.tool_category_id', $categoryId))
            ->when($branchId !== '', fn (Builder $q) => $q->where('tool.branch_id', $branchId));

        return DataTables::eloquent($query)
            ->filterColumn('tool.code', function (Builder $query, string $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $query->where(function (Builder $inner) use ($keyword, $needle): void {
                    $inner->where('tool.code', 'like', "%{$keyword}%")
                        ->orWhere('tool.serial_number', 'like', "%{$keyword}%")
                        ->orWhereRaw('LOWER(CAST(tool.name AS TEXT)) LIKE ?', [$needle]);
                });
            })
            ->addColumn('category', fn () => '')
            ->addColumn('custodian', fn () => '')
            ->addColumn('branch', fn () => '')
            ->addColumn('actions', fn () => '')
            ->rawColumns(['actions'])
            ->toJson();
    }
}
