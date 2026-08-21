<?php

namespace App\Http\Controllers\Reports;

use App\Application\Accounting\CashBookQueryService;
use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashBookController extends Controller
{
    public function __construct(
        private readonly CashBookQueryService $queryService,
    ) {}

    public function index(Request $request): Response
    {
        $cashAccounts = CashAccount::query()->where('is_active', true)->orderBy('code')->get();
        $cashAccountId = $request->query('cash_account_id', $cashAccounts->first()?->id);
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        $reportData = null;
        if ($cashAccountId) {
            $reportData = $this->queryService->getStatement($cashAccountId, $dateFrom, $dateTo);
        }

        return Inertia::render('Reports/CashBook', [
            'report' => $reportData,
            'cashAccounts' => $cashAccounts,
            'filters' => [
                'cash_account_id' => $cashAccountId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $cashAccountId = $request->query('cash_account_id');
        $dateFrom = $request->query('date_from', date('Y-01-01'));
        $dateTo = $request->query('date_to', date('Y-m-d'));

        if (! $cashAccountId) {
            abort(400, 'Cash account ID is required for export.');
        }

        $report = $this->queryService->getStatement($cashAccountId, $dateFrom, $dateTo);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="cash_book_'.$report['cash_account']['code'].'.csv"',
        ];

        $callback = function () use ($report): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Cash Book Report']);
            fputcsv($file, ['Cash Account', $report['cash_account']['code'].' - '.$report['cash_account']['name']]);
            fputcsv($file, ['Period', $report['date_from'].' to '.$report['date_to']]);
            fputcsv($file, ['Currency', $report['currency']]);
            fputcsv($file, []);
            fputcsv($file, ['Opening Balance', number_format($report['opening_balance_minor'] / 100, 2)]);
            fputcsv($file, []);
            fputcsv($file, ['Date', 'Journal Ref', 'Line Description', 'Receipts (In)', 'Payments (Out)', 'Running Balance']);

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
