<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\SupplierStatementReportService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierStatementController extends Controller
{
    public function __construct(
        private readonly SupplierStatementReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $request->query('currency', 'EGP');

        $reportData = null;
        if ($supplierId) {
            $reportData = $this->service->generate($supplierId, $dateFrom, $dateTo, $currency);
        }

        $suppliers = Supplier::query()->where('status', 'active')->orderBy('code')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('Reports/SupplierStatement', [
            'report' => $reportData,
            'suppliers' => $suppliers,
            'currencies' => $currencies,
            'filters' => [
                'supplier_id' => $supplierId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $supplierId = $request->query('supplier_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));
        $currency = $request->query('currency', 'EGP');

        if (! $supplierId) {
            abort(400, 'Supplier ID is required for export.');
        }

        $report = $this->service->generate($supplierId, $dateFrom, $dateTo, $currency);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="supplier_statement_'.$report['supplier']['code'].'.csv"',
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Supplier Statement Report']);
            fputcsv($file, ['Supplier', $report['supplier']['code'].' - '.$report['supplier']['name']]);
            fputcsv($file, ['Period', $report['filters']['date_from'].' to '.$report['filters']['date_to']]);
            fputcsv($file, ['Currency', $report['filters']['currency']]);
            fputcsv($file, []);
            fputcsv($file, ['Opening Balance', number_format($report['opening_balance_minor'] / 100, 2)]);
            fputcsv($file, []);
            fputcsv($file, ['Date', 'Type', 'Reference', 'Description', 'Debit (Payment)', 'Credit (Increase)', 'Running Balance']);

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
