<?php

namespace App\Http\Controllers\Reports;

use App\Application\Accounting\BankBookQueryService;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankBookController extends Controller
{
    public function __construct(
        private readonly BankBookQueryService $queryService,
    ) {}

    public function index(Request $request): Response
    {
        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('code')->get();
        $bankAccountId = $request->query('bank_account_id', $bankAccounts->first()?->id);
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        $reportData = null;
        if ($bankAccountId) {
            $reportData = $this->queryService->getStatement($bankAccountId, $dateFrom, $dateTo);
        }

        return Inertia::render('Reports/BankBook', [
            'report' => $reportData,
            'bankAccounts' => $bankAccounts,
            'filters' => [
                'bank_account_id' => $bankAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $bankAccountId = $request->query('bank_account_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        if (! $bankAccountId) {
            abort(400, 'Bank account ID is required for export.');
        }

        $report = $this->queryService->getStatement($bankAccountId, $dateFrom, $dateTo);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bank_book_'.$report['bank_account']['code'].'.csv"',
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Bank Book Report']);
            fputcsv($file, ['Bank Account', $report['bank_account']['code'].' - '.$report['bank_account']['name']]);
            fputcsv($file, ['Period', $report['date_from'].' to '.$report['date_to']]);
            fputcsv($file, ['Currency', $report['currency']]);
            fputcsv($file, []);
            fputcsv($file, ['Opening Balance', number_format($report['opening_balance_minor'] / 100, 2)]);
            fputcsv($file, []);
            fputcsv($file, ['Date', 'Journal Ref', 'Line Description', 'Deposits (In)', 'Withdrawals (Out)', 'Running Balance']);

            foreach ($report['entries'] as $item) {
                fputcsv($file, [
                    $item['entry_date'],
                    $item['journal_number'],
                    $item['description'],
                    number_format($item['debit_minor'] / 100, 2),
                    number_format($item['credit_minor'] / 100, 2),
                    number_format($item['balance_after_minor'] / 100, 2),
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Totals', '', '', number_format($report['period_debit_minor'] / 100, 2), number_format($report['period_credit_minor'] / 100, 2), number_format($report['closing_balance_minor'] / 100, 2)]);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
