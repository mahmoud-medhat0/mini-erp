<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\FinancialPeriodReportOptions;
use App\Application\Reports\ProjectProfitabilityCsvExporter;
use App\Application\Reports\ProjectProfitabilityReportService;
use App\Application\Reports\ReportPageOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectProfitabilityReportController extends Controller
{
    public function __construct(
        private readonly ProjectProfitabilityCsvExporter $csvExporter,
        private readonly ReportPageOptions $options,
        private readonly FinancialPeriodReportOptions $periodOptions,
    ) {}

    public function index(Request $request, ProjectProfitabilityReportService $service): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $filters = $this->validatedFilters($request);

        return Inertia::render('Reports/ProjectProfitability', [
            'reportData' => $service->generate(
                projectId: $filters['project_id'],
                costCenterId: $filters['cost_center_id'],
                accountId: $filters['account_id'],
                currency: $filters['currency'],
                dateFrom: $filters['date_from'],
                dateTo: $filters['date_to'],
                periodId: $filters['period_id'],
            ),
            'filters' => [
                'period_id' => $filters['period_id'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'project_id' => $filters['project_id'] ?? '',
                'cost_center_id' => $filters['cost_center_id'] ?? '',
                'account_id' => $filters['account_id'] ?? '',
                'currency' => $filters['currency'] ?? '',
            ],
            'projects' => $this->options->projects(['id', 'code', 'name', 'status', 'is_active']),
            'costCenters' => $this->options->costCenters(['id', 'code', 'name', 'is_active']),
            'accounts' => $this->options->accounts(['id', 'code', 'name', 'type', 'nature', 'is_active']),
            'currencies' => $this->options->currencies(['code', 'name', 'symbol']),
            'periods' => $this->periodOptions->all(),
        ]);
    }

    public function exportCsv(Request $request, ProjectProfitabilityReportService $service): StreamedResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        $filters = $this->validatedFilters($request);
        $report = $service->generate(
            projectId: $filters['project_id'],
            costCenterId: $filters['cost_center_id'],
            accountId: $filters['account_id'],
            currency: $filters['currency'],
            dateFrom: $filters['date_from'],
            dateTo: $filters['date_to'],
            periodId: $filters['period_id'],
        );

        return $this->csvExporter->export($report);
    }

    /**
     * @return array{
     *     period_id: ?string,
     *     date_from: ?string,
     *     date_to: ?string,
     *     project_id: ?string,
     *     cost_center_id: ?string,
     *     account_id: ?string,
     *     currency: ?string
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'period_id' => ['nullable', 'uuid', 'exists:financial_period,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'project_id' => ['nullable', 'uuid', 'exists:project,id'],
            'cost_center_id' => ['nullable', 'uuid', 'exists:cost_center,id'],
            'account_id' => ['nullable', 'uuid', 'exists:account,id'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
        ]);

        return [
            'period_id' => $validated['period_id'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'cost_center_id' => $validated['cost_center_id'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'currency' => $validated['currency'] ?? null,
        ];
    }
}
