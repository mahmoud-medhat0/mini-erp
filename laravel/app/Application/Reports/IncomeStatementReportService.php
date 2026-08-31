<?php

namespace App\Application\Reports;

use App\Models\Account;
use App\Models\FinancialPeriod;
use App\Models\FinancialStatementLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncomeStatementReportService
{
    /**
     * Generate the Income Statement report for the specified date range or financial period.
     *
     * @return array{
     *     from_date: string,
     *     to_date: string,
     *     period_id: string|null,
     *     sections: array<string, array{
     *         code: string,
     *         lines: list<array{
     *             id: string,
     *             code: string,
     *             name: mixed,
     *             section_code: string,
     *             normal_balance: string,
     *             total_minor: int,
     *             accounts: list<array{
     *                 id: string,
     *                 code: string,
     *                 name: mixed,
     *                 debit_minor: int,
     *                 credit_minor: int,
     *                 net_minor: int
     *             }>
     *         }>,
     *         total_minor: int
     *     }>,
     *     summary: array{
     *         total_revenue_minor: int,
     *         total_contra_revenue_minor: int,
     *         net_revenue_minor: int,
     *         total_cogs_minor: int,
     *         gross_profit_minor: int,
     *         total_operating_expenses_minor: int,
     *         operating_income_minor: int,
     *         total_other_income_minor: int,
     *         total_other_expenses_minor: int,
     *         net_income_minor: int
     *     },
     *     unmapped_accounts: list<array{
     *         id: string,
     *         code: string,
     *         name: mixed,
     *         type: string,
     *         debit_minor: int,
     *         credit_minor: int,
     *         net_minor: int
     *     }>,
     *     has_unmapped_warning: bool
     * }
     */
    public function generate(?string $fromDate = null, ?string $toDate = null, ?string $periodId = null): array
    {
        if ($periodId) {
            $period = FinancialPeriod::query()->where('id', $periodId)->first();
            if ($period) {
                $fromDate = $period->start_date->format('Y-m-d');
                $toDate = $period->end_date->format('Y-m-d');
            }
        }

        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : Carbon::now()->startOfYear();
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::now()->endOfDay();

        $fromDateStr = $from->format('Y-m-d');
        $toDateStr = $to->format('Y-m-d');

        $ledgerTotals = DB::table('ledger_entry')
            ->select('account_id')
            ->selectRaw('COALESCE(SUM(debit_minor), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) as total_credit')
            ->where('entry_date', '>=', $fromDateStr)
            ->where('entry_date', '<=', $toDateStr)
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $statementLines = FinancialStatementLine::query()
            ->where('statement_type', 'income_statement')
            ->where('is_active', true)
            ->with(['accounts' => function ($q) {
                $q->where('is_active', true)->orderBy('code');
            }])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $sectionsData = [
            'revenue' => ['code' => 'revenue', 'lines' => [], 'total_minor' => 0],
            'contra_revenue' => ['code' => 'contra_revenue', 'lines' => [], 'total_minor' => 0],
            'cogs' => ['code' => 'cogs', 'lines' => [], 'total_minor' => 0],
            'operating_expenses' => ['code' => 'operating_expenses', 'lines' => [], 'total_minor' => 0],
            'other_income' => ['code' => 'other_income', 'lines' => [], 'total_minor' => 0],
            'other_expenses' => ['code' => 'other_expenses', 'lines' => [], 'total_minor' => 0],
        ];

        foreach ($statementLines as $line) {
            $lineAccountsData = [];
            $lineTotalMinor = 0;

            foreach ($line->accounts as $account) {
                $totals = $ledgerTotals->get($account->id);
                $dr = (int) ($totals->total_debit ?? 0);
                $cr = (int) ($totals->total_credit ?? 0);

                if ($line->normal_balance === 'credit') {
                    $netMinor = $cr - $dr;
                } else {
                    $netMinor = $dr - $cr;
                }

                $lineTotalMinor += $netMinor;

                $lineAccountsData[] = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->getTranslations('name'),
                    'debit_minor' => $dr,
                    'credit_minor' => $cr,
                    'net_minor' => $netMinor,
                ];
            }

            $sectionKey = $line->section_code;
            if (! isset($sectionsData[$sectionKey])) {
                $sectionsData[$sectionKey] = ['code' => $sectionKey, 'lines' => [], 'total_minor' => 0];
            }

            $sectionsData[$sectionKey]['lines'][] = [
                'id' => $line->id,
                'code' => $line->code,
                'name' => $line->getTranslations('name'),
                'section_code' => $line->section_code,
                'normal_balance' => $line->normal_balance,
                'total_minor' => $lineTotalMinor,
                'accounts' => $lineAccountsData,
            ];

            $sectionsData[$sectionKey]['total_minor'] += $lineTotalMinor;
        }

        $totalRevenue = (int) ($sectionsData['revenue']['total_minor'] ?? 0);
        $totalContraRevenue = (int) ($sectionsData['contra_revenue']['total_minor'] ?? 0);
        $netRevenue = $totalRevenue - $totalContraRevenue;

        $totalCogs = (int) ($sectionsData['cogs']['total_minor'] ?? 0);
        $grossProfit = $netRevenue - $totalCogs;

        $totalOperatingExpenses = (int) ($sectionsData['operating_expenses']['total_minor'] ?? 0);
        $operatingIncome = $grossProfit - $totalOperatingExpenses;

        $totalOtherIncome = (int) ($sectionsData['other_income']['total_minor'] ?? 0);
        $totalOtherExpenses = (int) ($sectionsData['other_expenses']['total_minor'] ?? 0);

        $netIncome = $operatingIncome + $totalOtherIncome - $totalOtherExpenses;

        $unmappedAccounts = Account::query()
            ->whereNull('financial_statement_line_id')
            ->where('is_active', true)
            ->whereIn('type', ['revenue', 'expense', 'contra_revenue'])
            ->orderBy('code')
            ->get();

        $unmappedAccountsData = [];
        foreach ($unmappedAccounts as $unmappedAccount) {
            $totals = $ledgerTotals->get($unmappedAccount->id);
            if (! $totals) {
                continue;
            }

            $dr = (int) ($totals->total_debit ?? 0);
            $cr = (int) ($totals->total_credit ?? 0);
            if ($dr === 0 && $cr === 0) {
                continue;
            }

            $normalBalance = match ($unmappedAccount->type) {
                'revenue' => 'credit',
                'expense', 'contra_revenue' => 'debit',
                default => 'debit',
            };

            $netMinor = ($normalBalance === 'credit') ? ($cr - $dr) : ($dr - $cr);

            $unmappedAccountsData[] = [
                'id' => $unmappedAccount->id,
                'code' => $unmappedAccount->code,
                'name' => $unmappedAccount->getTranslations('name'),
                'type' => $unmappedAccount->type,
                'debit_minor' => $dr,
                'credit_minor' => $cr,
                'net_minor' => $netMinor,
            ];
        }

        return [
            'from_date' => $fromDateStr,
            'to_date' => $toDateStr,
            'period_id' => $periodId,
            'sections' => $sectionsData,
            'summary' => [
                'total_revenue_minor' => $totalRevenue,
                'total_contra_revenue_minor' => $totalContraRevenue,
                'net_revenue_minor' => $netRevenue,
                'total_cogs_minor' => $totalCogs,
                'gross_profit_minor' => $grossProfit,
                'total_operating_expenses_minor' => $totalOperatingExpenses,
                'operating_income_minor' => $operatingIncome,
                'total_other_income_minor' => $totalOtherIncome,
                'total_other_expenses_minor' => $totalOtherExpenses,
                'net_income_minor' => $netIncome,
            ],
            'unmapped_accounts' => $unmappedAccountsData,
            'has_unmapped_warning' => count($unmappedAccountsData) > 0,
        ];
    }
}
