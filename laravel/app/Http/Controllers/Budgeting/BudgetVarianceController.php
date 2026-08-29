<?php

namespace App\Http\Controllers\Budgeting;

use App\Application\Budgeting\BudgetVarianceCsvExporter;
use App\Application\Budgeting\BudgetVariancePageData;
use App\Application\Budgeting\BudgetVarianceReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetVarianceController extends Controller
{
    public function __construct(
        private readonly BudgetVarianceReportService $service,
        private readonly BudgetVariancePageData $pageData,
        private readonly BudgetVarianceCsvExporter $csvExporter,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('budgeting.view');
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $filters = $this->validatedFilters($request);

        $report = $this->service->generate(
            budgetId: $filters['budget_id'],
            fiscalYearId: $filters['fiscal_year_id'],
            periodId: $filters['period_id'],
            fromDate: $filters['from_date'],
            toDate: $filters['to_date'],
            accountId: $filters['account_id'],
            projectId: $filters['project_id'],
            costCenterId: $filters['cost_center_id'],
            currency: $filters['currency'],
        );

        $options = $this->pageData->options();

        return Inertia::render('Budgeting/Variance', [
            'report' => $report,
            'filters' => [
                'budget_id' => $filters['budget_id'] ?? '',
                'fiscal_year_id' => $filters['fiscal_year_id'] ?? '',
                'period_id' => $filters['period_id'] ?? '',
                'from_date' => $filters['from_date'] ?? '',
                'to_date' => $filters['to_date'] ?? '',
                'account_id' => $filters['account_id'] ?? '',
                'project_id' => $filters['project_id'] ?? '',
                'cost_center_id' => $filters['cost_center_id'] ?? '',
                'currency' => $filters['currency'] ?? '',
            ],
            'options' => $options,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('budgeting.export');
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        $filters = $this->validatedFilters($request);

        $report = $this->service->generate(
            budgetId: $filters['budget_id'],
            fiscalYearId: $filters['fiscal_year_id'],
            periodId: $filters['period_id'],
            fromDate: $filters['from_date'],
            toDate: $filters['to_date'],
            accountId: $filters['account_id'],
            projectId: $filters['project_id'],
            costCenterId: $filters['cost_center_id'],
            currency: $filters['currency'],
        );

        return $this->csvExporter->export($report);
    }

    /**
     * @return array{
     *     budget_id: ?string,
     *     fiscal_year_id: ?string,
     *     period_id: ?string,
     *     from_date: ?string,
     *     to_date: ?string,
     *     account_id: ?string,
     *     project_id: ?string,
     *     cost_center_id: ?string,
     *     currency: ?string
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'budget_id' => ['nullable', 'uuid', 'exists:budget,id'],
            'fiscal_year_id' => ['nullable', 'uuid', 'exists:fiscal_year,id'],
            'period_id' => ['nullable', 'uuid', 'exists:financial_period,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'account_id' => ['nullable', 'uuid', 'exists:account,id'],
            'project_id' => ['nullable', 'uuid', 'exists:project,id'],
            'cost_center_id' => ['nullable', 'uuid', 'exists:cost_center,id'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
        ]);

        return [
            'budget_id' => $validated['budget_id'] ?? null,
            'fiscal_year_id' => $validated['fiscal_year_id'] ?? null,
            'period_id' => $validated['period_id'] ?? null,
            'from_date' => $validated['from_date'] ?? null,
            'to_date' => $validated['to_date'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'cost_center_id' => $validated['cost_center_id'] ?? null,
            'currency' => $validated['currency'] ?? null,
        ];
    }
}
