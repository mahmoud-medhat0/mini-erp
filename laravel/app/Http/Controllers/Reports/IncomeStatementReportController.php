<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\IncomeStatementReportService;
use App\Http\Controllers\Controller;
use App\Models\FinancialPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeStatementReportController extends Controller
{
    public function __construct(
        private IncomeStatementReportService $service
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

        return Inertia::render('Reports/IncomeStatement', [
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

        $filename = "income_statement_{$report['from_date']}_to_{$report['to_date']}.csv";

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['INCOME STATEMENT REPORT', "Period: {$report['from_date']} to {$report['to_date']}"]);
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
            fputcsv($handle, ['Gross Revenue (Minor)', $report['summary']['total_revenue_minor']]);
            fputcsv($handle, ['Sales Returns & Allowances (Minor)', $report['summary']['total_contra_revenue_minor']]);
            fputcsv($handle, ['NET REVENUE (Minor)', $report['summary']['net_revenue_minor']]);
            fputcsv($handle, ['Cost of Goods Sold (Minor)', $report['summary']['total_cogs_minor']]);
            fputcsv($handle, ['GROSS PROFIT (Minor)', $report['summary']['gross_profit_minor']]);
            fputcsv($handle, ['Operating Expenses (Minor)', $report['summary']['total_operating_expenses_minor']]);
            fputcsv($handle, ['OPERATING INCOME (Minor)', $report['summary']['operating_income_minor']]);
            fputcsv($handle, ['Other Income (Minor)', $report['summary']['total_other_income_minor']]);
            fputcsv($handle, ['Other Expenses (Minor)', $report['summary']['total_other_expenses_minor']]);
            fputcsv($handle, ['NET INCOME / (LOSS) (Minor)', $report['summary']['net_income_minor']]);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
