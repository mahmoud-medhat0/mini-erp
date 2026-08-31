<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\RentalOperationsCsvExporter;
use App\Application\Reports\RentalOperationsReportPageData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalOperationsReportController extends Controller
{
    public function __construct(
        private readonly RentalOperationsReportPageData $pageData,
        private readonly RentalOperationsCsvExporter $csvExporter,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view_financials');

        return Inertia::render('Reports/RentalOperationsReport', $this->pageData->indexData($this->validatedFilters($request)));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        return $this->csvExporter->export($this->validatedFilters($request));
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'as_of_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'uuid', 'exists:branch,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customer,id'],
            'status' => ['nullable', 'string', 'in:draft,submitted,approved,active,completed,cancelled'],
            'currency' => ['nullable', 'string', 'size:3', 'exists:currency,code'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper((string) $validated['currency']);
        }

        return $validated;
    }
}
