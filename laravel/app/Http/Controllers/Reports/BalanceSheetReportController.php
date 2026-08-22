<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\BalanceSheetReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetReportController extends Controller
{
    public function __construct(
        private BalanceSheetReportService $service
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $asOfDate = (string) $request->query('as_of_date', date('Y-m-d'));
        $report = $this->service->generate($asOfDate);

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $report,
            'filters' => [
                'as_of_date' => $asOfDate,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        $asOfDate = (string) $request->query('as_of_date', date('Y-m-d'));
        $report = $this->service->generate($asOfDate);

        $filename = "balance_sheet_{$asOfDate}.csv";

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['BALANCE SHEET REPORT', "As Of: {$report['as_of_date']}"]);
            fputcsv($handle, []);

            fputcsv($handle, ['Section', 'Line Code', 'Line Name', 'Account Code', 'Account Name', 'Debit (Minor)', 'Credit (Minor)', 'Net Amount (Minor)']);

            foreach ($report['sections'] as $sectionKey => $section) {
                foreach ($section['lines'] as $line) {
                    $lineName = is_array($line['name']) ? ($line['name']['en'] ?? reset($line['name'])) : $line['name'];
                    foreach ($line['accounts'] as $acc) {
                        $accName = is_array($acc['name']) ? ($acc['name']['en'] ?? reset($acc['name'])) : $acc['name'];
                        fputcsv($handle, [
                            $sectionKey,
                            $line['code'],
                            $lineName,
                            $acc['code'],
                            $accName,
                            $acc['debit_minor'],
                            $acc['credit_minor'],
                            $acc['net_minor'],
                        ]);
                    }
                    fputcsv($handle, [$sectionKey, $line['code'], "Total {$lineName}", '', '', '', '', $line['total_minor']]);
                }
                fputcsv($handle, [$sectionKey, '', "SECTION TOTAL ({$sectionKey})", '', '', '', '', $section['total_minor']]);
                fputcsv($handle, []);
            }

            fputcsv($handle, ['SUMMARY']);
            fputcsv($handle, ['Total Current Assets (Minor)', $report['summary']['total_current_assets_minor']]);
            fputcsv($handle, ['Total Non-Current Assets (Minor)', $report['summary']['total_non_current_assets_minor']]);
            fputcsv($handle, ['TOTAL ASSETS (Minor)', $report['summary']['total_assets_minor']]);
            fputcsv($handle, ['Total Current Liabilities (Minor)', $report['summary']['total_current_liabilities_minor']]);
            fputcsv($handle, ['Total Non-Current Liabilities (Minor)', $report['summary']['total_non_current_liabilities_minor']]);
            fputcsv($handle, ['Total Liabilities (Minor)', $report['summary']['total_liabilities_minor']]);
            fputcsv($handle, ['Total Equity (Minor)', $report['summary']['total_equity_minor']]);
            fputcsv($handle, ['Current Period Net Income (Minor)', $report['summary']['current_period_net_income_minor']]);
            fputcsv($handle, ['Total Equity including Net Income (Minor)', $report['summary']['total_equity_including_net_income_minor']]);
            fputcsv($handle, ['TOTAL LIABILITIES & EQUITY (Minor)', $report['summary']['total_liabilities_and_equity_minor']]);
            fputcsv($handle, ['Is Balanced', $report['summary']['is_balanced'] ? 'YES' : 'NO']);
            fputcsv($handle, ['Imbalance (Minor)', $report['summary']['imbalance_minor']]);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
