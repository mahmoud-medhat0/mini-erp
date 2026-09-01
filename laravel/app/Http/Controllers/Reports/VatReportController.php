<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\VatCsvReportExporter;
use App\Application\Reports\VatReportPageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatReportController extends Controller
{
    public function __construct(
        private readonly VatReportPageData $pageData,
        private readonly VatCsvReportExporter $csvExporter,
    ) {}

    public function register(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render(
            'Reports/VatRegister',
            $this->pageData->register($this->filters($request, ['from_date', 'to_date', 'type', 'tax_code_id']))
        );
    }

    public function exportRegister(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->register($this->filters($request, ['from_date', 'to_date', 'type', 'tax_code_id']));
    }

    public function summary(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render('Reports/VatSummary', $this->pageData->summary($this->filters($request, ['from_date', 'to_date'])));
    }

    public function exportSummary(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->summary($this->filters($request, ['from_date', 'to_date']));
    }

    public function reconciliation(ReportFilterRequest $request): Response
    {
        $this->authorizeReportView();

        return Inertia::render(
            'Reports/VatGlReconciliation',
            $this->pageData->reconciliation($this->filters($request, ['from_date', 'to_date', 'currency']))
        );
    }

    public function exportReconciliation(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        return $this->csvExporter->reconciliation($this->filters($request, ['from_date', 'to_date', 'currency']));
    }

    private function filters(ReportFilterRequest $request, array $allowed): array
    {
        return array_filter(
            $request->only($allowed),
            fn ($value): bool => $value !== null && $value !== ''
        );
    }

    private function authorizeReportView(): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');
    }

    private function authorizeReportExport(ReportFilterRequest $request): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $user = $request->user();
        if (! $user || (! $user->can('reports.export') && ! $user->can('taxes.view'))) {
            abort(403);
        }
    }
}
