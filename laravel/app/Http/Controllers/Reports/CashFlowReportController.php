<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\CashFlowReportService;
use App\Http\Controllers\Controller;
use App\Models\FinancialPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowReportController extends Controller
{
    public function __construct(
        private CashFlowReportService $service
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');
        Gate::authorize('view_financials');

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $periodId = $request->query('period_id');

        $report = $this->service->generate(
            $fromDate ? (string) $fromDate : null,
            $toDate ? (string) $toDate : null,
            $periodId ? (string) $periodId : null
        );

        $periods = FinancialPeriod::query()
            ->with('fiscalYear')
            ->orderBy('start_date', 'desc')
            ->get(['id', 'fiscal_year_id', 'month', 'start_date', 'end_date', 'status'])
            ->map(fn (FinancialPeriod $period): array => [
                'id' => $period->id,
                'year' => $period->fiscalYear?->year,
                'month' => $period->month,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date' => $period->end_date?->format('Y-m-d'),
                'status' => $period->status,
            ])
            ->values();

        return Inertia::render('Reports/CashFlow', [
            'report' => $report,
            'periods' => $periods,
            'filters' => [
                'from_date' => $report['from_date'],
                'to_date' => $report['to_date'],
                'period_id' => $report['period_id'],
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('reports.view');
        Gate::authorize('reports.export');
        Gate::authorize('view_financials');

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $periodId = $request->query('period_id');

        $report = $this->service->generate(
            $fromDate ? (string) $fromDate : null,
            $toDate ? (string) $toDate : null,
            $periodId ? (string) $periodId : null
        );

        $filename = "cash_flow_{$report['from_date']}_to_{$report['to_date']}.csv";

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['CASH FLOW STATEMENT REPORT', "Period: {$report['from_date']} to {$report['to_date']}"]);
            fputcsv($handle, []);

            fputcsv($handle, ['CASH FLOW SUMMARY', 'AMOUNT (Minor)']);
            fputcsv($handle, ['Opening Cash Balance', $report['opening_cash_minor']]);
            fputcsv($handle, []);

            fputcsv($handle, ['OPERATING ACTIVITIES']);
            fputcsv($handle, ['Operating Cash Inflows', $report['operating']['inflows_minor']]);
            fputcsv($handle, ['Operating Cash Outflows', $report['operating']['outflows_minor']]);
            fputcsv($handle, ['NET CASH FROM OPERATING ACTIVITIES', $report['operating']['net_minor']]);
            fputcsv($handle, []);

            fputcsv($handle, ['INVESTING ACTIVITIES']);
            fputcsv($handle, ['Investing Cash Inflows', $report['investing']['inflows_minor']]);
            fputcsv($handle, ['Investing Cash Outflows', $report['investing']['outflows_minor']]);
            fputcsv($handle, ['NET CASH FROM INVESTING ACTIVITIES', $report['investing']['net_minor']]);
            fputcsv($handle, []);

            fputcsv($handle, ['FINANCING ACTIVITIES']);
            fputcsv($handle, ['Financing Cash Inflows', $report['financing']['inflows_minor']]);
            fputcsv($handle, ['Financing Cash Outflows', $report['financing']['outflows_minor']]);
            fputcsv($handle, ['NET CASH FROM FINANCING ACTIVITIES', $report['financing']['net_minor']]);
            fputcsv($handle, []);

            if ($report['unclassified']['net_minor'] !== 0 || ! empty($report['unclassified_warnings'])) {
                fputcsv($handle, ['UNCLASSIFIED CASH MOVEMENTS']);
                fputcsv($handle, ['Unclassified Inflows', $report['unclassified']['inflows_minor']]);
                fputcsv($handle, ['Unclassified Outflows', $report['unclassified']['outflows_minor']]);
                fputcsv($handle, ['NET UNCLASSIFIED CASH', $report['unclassified']['net_minor']]);
                fputcsv($handle, []);
            }

            fputcsv($handle, ['RECONCILIATION SUMMARY']);
            fputcsv($handle, ['Net Increase / (Decrease) in Cash', $report['net_cash_change_minor']]);
            fputcsv($handle, ['Closing Cash Balance', $report['closing_cash_minor']]);
            fputcsv($handle, ['Reconciled Closing Cash Balance', $report['reconciled_closing_cash_minor']]);
            fputcsv($handle, ['Is Reconciled', $report['is_reconciled'] ? 'YES' : 'NO']);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
