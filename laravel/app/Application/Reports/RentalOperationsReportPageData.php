<?php

namespace App\Application\Reports;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;

class RentalOperationsReportPageData
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'active', 'completed', 'cancelled'];

    public function __construct(private readonly RentalOperationsReportService $reportService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        return [
            'reportData' => $this->reportService->generate($filters),
            'filters' => $this->normalizedFilters($filters),
            'branches' => Branch::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'customers' => Customer::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => self::STATUSES,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizedFilters(array $filters): array
    {
        return [
            'as_of_date' => $filters['as_of_date'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'branch_id' => $filters['branch_id'] ?? '',
            'customer_id' => $filters['customer_id'] ?? '',
            'status' => $filters['status'] ?? '',
            'currency' => $filters['currency'] ?? '',
            'search' => $filters['search'] ?? '',
        ];
    }
}
