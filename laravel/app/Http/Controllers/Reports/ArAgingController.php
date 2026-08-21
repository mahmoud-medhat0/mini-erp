<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ArAgingReportService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArAgingController extends Controller
{
    public function __construct(
        private readonly ArAgingReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $customerId = $request->query('customer_id');
        $currency = $request->query('currency', 'EGP');

        $report = $this->service->generate($asOfDate, $customerId, $currency);

        $customers = Customer::query()->where('status', 'active')->orderBy('code')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('Reports/ArAging', [
            'report' => $report,
            'customers' => $customers,
            'currencies' => $currencies,
            'filters' => [
                'as_of_date' => $asOfDate,
                'customer_id' => $customerId,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $asOfDate = $request->query('as_of_date', date('Y-m-d'));
        $customerId = $request->query('customer_id');
        $currency = $request->query('currency', 'EGP');

        $report = $this->service->generate($asOfDate, $customerId, $currency);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ar_aging_'.$asOfDate.'.csv"',
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['AR Aging Report']);
            fputcsv($file, ['As Of Date', $report['as_of_date']]);
            fputcsv($file, ['Currency', $report['currency']]);
            fputcsv($file, []);
            fputcsv($file, ['Customer Code', 'Customer Name', 'Document Ref', 'Entry Date', 'Due Date', 'Basis Used', 'Age (Days)', 'Original Amount', 'Allocated Amount', 'Open Balance', 'Bucket']);

            foreach ($report['customers'] as $cGroup) {
                foreach ($cGroup['items'] as $item) {
                    fputcsv($file, [
                        $cGroup['customer']['code'],
                        $cGroup['customer']['name'],
                        $item['reference'],
                        $item['entry_date'],
                        $item['due_date'] ?? 'N/A',
                        $item['basis_used'],
                        $item['age_days'],
                        number_format($item['original_amount_minor'] / 100, 2),
                        number_format($item['allocated_minor'] / 100, 2),
                        number_format($item['unapplied_minor'] / 100, 2),
                        $item['bucket'],
                    ]);
                }
            }

            fputcsv($file, []);
            fputcsv($file, ['Grand Totals', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', 'Over 90 Days', 'Total Open Balance']);
            fputcsv($file, [
                '',
                number_format($report['grand_totals']['current'] / 100, 2),
                number_format($report['grand_totals']['b1_30'] / 100, 2),
                number_format($report['grand_totals']['b31_60'] / 100, 2),
                number_format($report['grand_totals']['b61_90'] / 100, 2),
                number_format($report['grand_totals']['over_90'] / 100, 2),
                number_format($report['grand_totals']['total'] / 100, 2),
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
