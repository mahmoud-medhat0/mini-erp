<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\CustomerStatementReportService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerStatementReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        $customerId = $request->query('customer_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $request->query('currency', 'EGP');

        $reportData = null;
        if ($customerId) {
            $reportData = $this->service->generate($customerId, $dateFrom, $dateTo, $currency);
        }

        $customers = Customer::query()->where('status', 'active')->orderBy('code')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('Reports/CustomerStatement', [
            'report' => $reportData,
            'customers' => $customers,
            'currencies' => $currencies,
            'filters' => [
                'customer_id' => $customerId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $customerId = $request->query('customer_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $request->query('currency', 'EGP');

        if (! $customerId) {
            abort(400, 'Customer ID is required for export.');
        }

        $report = $this->service->generate($customerId, $dateFrom, $dateTo, $currency);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customer_statement_'.$report['customer']['code'].'.csv"',
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Customer Statement Report']);
            fputcsv($file, ['Customer', $report['customer']['code'].' - '.$report['customer']['name']]);
            fputcsv($file, ['Period', $report['filters']['date_from'].' to '.$report['filters']['date_to']]);
            fputcsv($file, ['Currency', $report['filters']['currency']]);
            fputcsv($file, []);
            fputcsv($file, ['Opening Balance', number_format($report['opening_balance_minor'] / 100, 2)]);
            fputcsv($file, []);
            fputcsv($file, ['Date', 'Type', 'Reference', 'Description', 'Debit (Increase)', 'Credit (Payment)', 'Running Balance']);

            foreach ($report['lines'] as $line) {
                fputcsv($file, [
                    $line['date'],
                    $line['type'],
                    $line['reference'],
                    $line['description'],
                    number_format($line['debit_minor'] / 100, 2),
                    number_format($line['credit_minor'] / 100, 2),
                    number_format($line['running_balance_minor'] / 100, 2),
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Totals', '', '', '', number_format($report['total_debit_minor'] / 100, 2), number_format($report['total_credit_minor'] / 100, 2), number_format($report['closing_balance_minor'] / 100, 2)]);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
