<?php

namespace App\Http\Controllers\Reports;

use App\Application\Reports\ChequeRegisterReportService;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChequeRegisterReportController extends Controller
{
    public function __construct(
        private readonly ChequeRegisterReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        $direction = $request->query('direction', 'all');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $supplierId = $request->query('supplier_id');
        $bankAccountId = $request->query('bank_account_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $currency = $request->query('currency', 'EGP');

        $report = $this->service->generate(
            $direction,
            $status,
            $customerId,
            $supplierId,
            $bankAccountId,
            $dateFrom,
            $dateTo,
            $currency
        );

        $customers = Customer::query()->where('status', 'active')->orderBy('code')->get();
        $suppliers = Supplier::query()->where('status', 'active')->orderBy('code')->get();
        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('code')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        return Inertia::render('Reports/ChequeRegister', [
            'report' => $report,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'bankAccounts' => $bankAccounts,
            'currencies' => $currencies,
            'filters' => [
                'direction' => $direction,
                'status' => $status,
                'customer_id' => $customerId,
                'supplier_id' => $supplierId,
                'bank_account_id' => $bankAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency' => $currency,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $direction = $request->query('direction', 'all');
        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $supplierId = $request->query('supplier_id');
        $bankAccountId = $request->query('bank_account_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $currency = $request->query('currency', 'EGP');

        $report = $this->service->generate(
            $direction,
            $status,
            $customerId,
            $supplierId,
            $bankAccountId,
            $dateFrom,
            $dateTo,
            $currency
        );

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="cheque_register_report.csv"',
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Cheque Register Report']);
            fputcsv($file, ['Direction', strtoupper($report['direction'])]);
            fputcsv($file, ['Currency', $report['filters']['currency']]);
            fputcsv($file, []);
            fputcsv($file, ['Direction', 'Cheque Number', 'Party Code', 'Party Name', 'Bank Account', 'Due Date', 'Amount', 'Status']);

            foreach ($report['items'] as $item) {
                fputcsv($file, [
                    strtoupper($item['direction']),
                    $item['cheque_number'],
                    $item['party_code'],
                    $item['party_name'],
                    $item['bank_account_name'],
                    $item['due_date'],
                    number_format($item['amount_minor'] / 100, 2),
                    strtoupper($item['status']),
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Total Count', $report['total_count']]);
            fputcsv($file, ['Total Incoming', number_format($report['incoming_total_minor'] / 100, 2)]);
            fputcsv($file, ['Total Outgoing', number_format($report['outgoing_total_minor'] / 100, 2)]);
            fputcsv($file, ['Grand Total', number_format($report['total_amount_minor'] / 100, 2)]);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
