<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\BranchProfitabilityCsvExporter;
use App\Application\Reports\BranchProfitabilityReportService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchProfitabilityReportController extends Controller
{
    public function __construct(
        private readonly BranchProfitabilityCsvExporter $csvExporter,
        private readonly ReportPageOptions $options,
    ) {}

    public function index(Request $request, BranchProfitabilityReportService $service): Response
    {
        Gate::authorize('view_financials');

        [$branchId, $dateFrom, $dateTo] = $this->validatedFilters($request);

        return Inertia::render('Reports/BranchProfitability', [
            'reportData' => $service->generate(
                branchId: $branchId,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),
            'filters' => [
                'branch_id' => $branchId ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
            'branches' => $this->options->branches(['id', 'code', 'name', 'is_active']),
        ]);
    }

    public function exportCsv(Request $request, BranchProfitabilityReportService $service): StreamedResponse
    {
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        [$branchId, $dateFrom, $dateTo] = $this->validatedFilters($request);
        $report = $service->generate(
            branchId: $branchId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );

        return $this->csvExporter->export($report);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return [
            $validated['branch_id'] ?? null,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        ];
    }
}
