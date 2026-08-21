<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ApToGlReconciliationReportService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApToGlReconciliationController extends Controller
{
    public function __construct(
        private readonly ApToGlReconciliationReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $currency = $request->query('currency', 'EGP');

        $report = $this->service->generate($asOfDate, $currency);
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('Reports/ApGlReconciliation', [
            'report' => $report,
            'currencies' => $currencies,
            'filters' => [
                'as_of_date' => $asOfDate,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $currency = $request->query('currency', 'EGP');

        $report = $this->service->generate($asOfDate, $currency);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ap_to_gl_reconciliation_'.$asOfDate.'.csv"',
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['AP to GL Reconciliation Report']);
            fputcsv($file, ['As Of Date', $report['as_of_date']]);
            fputcsv($file, ['Currency', $report['currency']]);
            fputcsv($file, []);
            fputcsv($file, ['AP Subledger Total Balance', number_format($report['subledger_total_minor'] / 100, 2)]);
            fputcsv($file, ['AP Control GL Account Balance', number_format($report['gl_total_minor'] / 100, 2)]);
            fputcsv($file, ['Difference', number_format($report['difference_minor'] / 100, 2)]);
            fputcsv($file, ['Reconciled Status', $report['is_reconciled'] ? 'RECONCILED' : 'UNRECONCILED DIFFERENCE']);
            fputcsv($file, []);
            fputcsv($file, ['Supplier Code', 'Supplier Name', 'Subledger Balance']);

            foreach ($report['supplier_breakdown'] as $row) {
                fputcsv($file, [
                    $row['supplier_code'],
                    $row['supplier_name'],
                    number_format($row['subledger_balance_minor'] / 100, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
