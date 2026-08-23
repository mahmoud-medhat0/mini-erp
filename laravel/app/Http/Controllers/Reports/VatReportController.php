<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\VatRegisterReportService;
use App\Application\Reports\VatSummaryReportService;
use App\Application\Reports\VatToGlReconciliationService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\TaxCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatReportController extends Controller
{
    public function __construct(
        private readonly VatRegisterReportService $registerService,
        private readonly VatSummaryReportService $summaryService,
        private readonly VatToGlReconciliationService $reconciliationService,
    ) {}

    public function register(Request $request): Response
    {
        $this->authorizeReportView();

        $filters = $this->filters($request, ['from_date', 'to_date', 'type', 'tax_code_id']);
        $report = $this->registerService->generate($filters);
        $taxCodes = TaxCode::query()->where('is_active', true)->orderBy('code', 'asc')->get();

        return Inertia::render('Reports/VatRegister', [
            'report' => $report,
            'taxCodes' => $taxCodes,
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
                'type' => $report['type'],
                'tax_code_id' => $report['tax_code_id'],
            ],
        ]);
    }

    public function exportRegister(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        $filters = $this->filters($request, ['from_date', 'to_date', 'type', 'tax_code_id']);
        $report = $this->registerService->generate($filters);

        $filename = "vat_register_{$report['from_date']}_to_{$report['to_date']}.csv";

        return $this->csvResponse(
            $filename,
            ['Document Date', 'Document Type', 'Document Number', 'Entity Type', 'Entity Name', 'Tax Category', 'Tax Code', 'Rate Bps', 'Subtotal Minor', 'Tax Minor', 'Gross Minor'],
            $report['rows'],
            fn (array $row): array => [
                $row['document_date'],
                $row['document_type'],
                $row['document_number'],
                $row['entity_type'],
                $row['entity_name'],
                $row['tax_category'],
                $row['tax_code'],
                $row['tax_rate_bps'],
                $row['subtotal_minor'],
                $row['tax_amount_minor'],
                $row['gross_amount_minor'],
            ]
        );
    }

    public function summary(Request $request): Response
    {
        $this->authorizeReportView();

        $filters = $this->filters($request, ['from_date', 'to_date']);
        $report = $this->summaryService->generate($filters);

        return Inertia::render('Reports/VatSummary', [
            'report' => $report,
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
            ],
        ]);
    }

    public function exportSummary(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        $filters = $this->filters($request, ['from_date', 'to_date']);
        $report = $this->summaryService->generate($filters);

        $filename = "vat_summary_{$report['from_date']}_to_{$report['to_date']}.csv";

        return response()->stream(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['VAT Summary Report']);
            fputcsv($handle, ['From Date', $report['from_date'], 'To Date', $report['to_date']]);
            fputcsv($handle, []);

            fputcsv($handle, ['OUTPUT VAT SUMMARY']);
            fputcsv($handle, ['Tax Code', 'Tax Rate Bps', 'Subtotal Minor', 'Tax Minor', 'Gross Minor']);
            foreach ($report['output_vat_breakdown'] as $row) {
                fputcsv($handle, [$row['code'], $row['rate_bps'], $row['subtotal_minor'], $row['tax_amount_minor'], $row['gross_amount_minor']]);
            }
            fputcsv($handle, ['Total Output VAT', '', $report['summary']['total_output_subtotal_minor'], $report['summary']['total_output_tax_minor'], $report['summary']['total_output_gross_minor']]);
            fputcsv($handle, []);

            fputcsv($handle, ['INPUT VAT SUMMARY']);
            fputcsv($handle, ['Tax Code', 'Tax Rate Bps', 'Subtotal Minor', 'Tax Minor', 'Gross Minor']);
            foreach ($report['input_vat_breakdown'] as $row) {
                fputcsv($handle, [$row['code'], $row['rate_bps'], $row['subtotal_minor'], $row['tax_amount_minor'], $row['gross_amount_minor']]);
            }
            fputcsv($handle, ['Total Input VAT', '', $report['summary']['total_input_subtotal_minor'], $report['summary']['total_input_tax_minor'], $report['summary']['total_input_gross_minor']]);
            fputcsv($handle, []);

            fputcsv($handle, ['NET VAT PAYABLE', '', '', $report['summary']['net_vat_payable_minor']]);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function reconciliation(Request $request): Response
    {
        $this->authorizeReportView();

        $filters = $this->filters($request, ['from_date', 'to_date', 'currency']);
        $report = $this->reconciliationService->generate($filters);
        $currencies = Currency::query()->where('is_active', true)->orderBy('code', 'asc')->get();

        return Inertia::render('Reports/VatGlReconciliation', [
            'report' => $report,
            'currencies' => $currencies,
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
                'currency' => $report['currency'],
            ],
        ]);
    }

    public function exportReconciliation(Request $request): StreamedResponse
    {
        $this->authorizeReportExport($request);

        $filters = $this->filters($request, ['from_date', 'to_date', 'currency']);
        $report = $this->reconciliationService->generate($filters);

        $filename = "vat_gl_reconciliation_{$report['from_date']}_to_{$report['to_date']}.csv";

        return response()->stream(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['VAT to GL Reconciliation Report']);
            fputcsv($handle, ['From Date', $report['from_date'], 'To Date', $report['to_date'], 'Currency', $report['currency']]);
            fputcsv($handle, ['Reconciled Status', $report['is_reconciled'] ? 'RECONCILED' : 'UNRECONCILED DIFFERENCE']);
            fputcsv($handle, []);

            fputcsv($handle, ['Category', 'Register Tax Minor', 'GL Ledger Movement Minor', 'Signed Difference Minor']);
            fputcsv($handle, ['Output VAT', $report['register_output_tax_minor'], $report['gl_output_tax_minor'], $report['output_tax_difference_minor']]);
            fputcsv($handle, ['Input VAT', $report['register_input_tax_minor'], $report['gl_input_tax_minor'], $report['input_tax_difference_minor']]);
            fputcsv($handle, ['Net VAT', $report['register_net_vat_minor'], $report['gl_net_vat_minor'], $report['net_vat_difference_minor']]);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filters(Request $request, array $allowed): array
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

    private function authorizeReportExport(Request $request): void
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $user = $request->user();
        if (! $user || (! $user->can('reports.export') && ! $user->can('taxes.view'))) {
            abort(403);
        }
    }

    private function csvResponse(string $filename, array $headers, iterable $rows, callable $rowMapper): StreamedResponse
    {
        return response()->stream(function () use ($headers, $rows, $rowMapper): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $rowMapper($row));
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
