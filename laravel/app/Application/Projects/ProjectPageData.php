<?php

namespace App\Application\Projects;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     projects: LengthAwarePaginator,
     *     filters: array{search: string, status: string, is_billable: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $isBillable = trim((string) ($filters['is_billable'] ?? ''));

        $projects = Project::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($isBillable !== '', function ($query) use ($isBillable): void {
                $query->where('is_billable', filter_var($isBillable, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return [
            'projects' => $projects,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'is_billable' => $isBillable,
            ],
        ];
    }
}
