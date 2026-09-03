<?php

namespace App\Application\MasterData;

class CustomerPageData
{
    /**
     * The Customers page now uses Yajra DataTables via /customers/data for all
     * customer rows. The index action only needs to pass the initial filter state
     * (status) so the React component can pre-populate the filter toolbar.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'filters' => [
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ];
    }
}
