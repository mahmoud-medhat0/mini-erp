<?php

namespace App\Application\Reports;

use App\Models\Account;
use App\Models\FinancialStatementLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceSheetReportService
{
    /**
     * Generate the Balance Sheet report as of the specified date.
     *
     * @return array{
     *     as_of_date: string,
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
     *         total_current_assets_minor: int,
     *         total_non_current_assets_minor: int,
     *         total_assets_minor: int,
     *         total_current_liabilities_minor: int,
     *         total_non_current_liabilities_minor: int,
     *         total_liabilities_minor: int,
     *         total_equity_minor: int,
     *         current_period_net_income_minor: int,
     *         total_equity_including_net_income_minor: int,
     *         total_liabilities_and_equity_minor: int,
     *         is_balanced: bool,
     *         imbalance_minor: int
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
    public function generate(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();
        $asOfDateStr = $asOf->format('Y-m-d');

        $ledgerTotals = DB::table('ledger_entry')
            ->select('account_id')
            ->selectRaw('COALESCE(SUM(debit_minor), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) as total_credit')
            ->where('entry_date', '<=', $asOfDateStr)
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $statementLines = FinancialStatementLine::query()
            ->where('statement_type', 'balance_sheet')
            ->where('is_active', true)
            ->with(['accounts' => function ($q) {
                $q->where('is_active', true)->orderBy('code');
            }])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $sectionsData = [
            'current_assets' => ['code' => 'current_assets', 'lines' => [], 'total_minor' => 0],
            'non_current_assets' => ['code' => 'non_current_assets', 'lines' => [], 'total_minor' => 0],
            'current_liabilities' => ['code' => 'current_liabilities', 'lines' => [], 'total_minor' => 0],
            'non_current_liabilities' => ['code' => 'non_current_liabilities', 'lines' => [], 'total_minor' => 0],
            'equity' => ['code' => 'equity', 'lines' => [], 'total_minor' => 0],
        ];

        foreach ($statementLines as $line) {
            $lineAccountsData = [];
            $lineTotalMinor = 0;

            foreach ($line->accounts as $account) {
                $totals = $ledgerTotals->get($account->id);
                $dr = (int) ($totals->total_debit ?? 0);
                $cr = (int) ($totals->total_credit ?? 0);

                if ($line->normal_balance === 'debit') {
                    $netMinor = $dr - $cr;
                } else {
                    $netMinor = $cr - $dr;
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

        $totalCurrentAssets = (int) ($sectionsData['current_assets']['total_minor'] ?? 0);
        $totalNonCurrentAssets = (int) ($sectionsData['non_current_assets']['total_minor'] ?? 0);
        $totalAssets = $totalCurrentAssets + $totalNonCurrentAssets;

        $totalCurrentLiabilities = (int) ($sectionsData['current_liabilities']['total_minor'] ?? 0);
        $totalNonCurrentLiabilities = (int) ($sectionsData['non_current_liabilities']['total_minor'] ?? 0);
        $totalLiabilities = $totalCurrentLiabilities + $totalNonCurrentLiabilities;

        $totalEquityBase = (int) ($sectionsData['equity']['total_minor'] ?? 0);

        $incomeStatementTotals = DB::table('ledger_entry')
            ->join('account', 'ledger_entry.account_id', '=', 'account.id')
            ->leftJoin('financial_statement_line', 'account.financial_statement_line_id', '=', 'financial_statement_line.id')
            ->selectRaw('COALESCE(SUM(debit_minor), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) as total_credit')
            ->where('ledger_entry.entry_date', '<=', $asOfDateStr)
            ->where(function ($query) {
                $query->where('financial_statement_line.statement_type', 'income_statement')
                    ->orWhere(function ($q) {
                        $q->whereNull('account.financial_statement_line_id')
                            ->whereIn('account.type', ['revenue', 'expense', 'contra_revenue']);
                    });
            })
            ->first();

        $isDebit = (int) ($incomeStatementTotals->total_debit ?? 0);
        $isCredit = (int) ($incomeStatementTotals->total_credit ?? 0);
        $currentPeriodNetIncome = $isCredit - $isDebit;

        $totalEquityIncludingNetIncome = $totalEquityBase + $currentPeriodNetIncome;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityIncludingNetIncome;

        $imbalanceMinor = $totalAssets - $totalLiabilitiesAndEquity;
        $isBalanced = ($imbalanceMinor === 0);

        $unmappedAccounts = Account::query()
            ->whereNull('financial_statement_line_id')
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity', 'contra_asset', 'contra_liability'])
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
                'asset', 'contra_liability' => 'debit',
                'liability', 'equity', 'contra_asset' => 'credit',
                default => 'debit',
            };

            $netMinor = ($normalBalance === 'debit') ? ($dr - $cr) : ($cr - $dr);

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
            'as_of_date' => $asOfDateStr,
            'sections' => $sectionsData,
            'summary' => [
                'total_current_assets_minor' => $totalCurrentAssets,
                'total_non_current_assets_minor' => $totalNonCurrentAssets,
                'total_assets_minor' => $totalAssets,
                'total_current_liabilities_minor' => $totalCurrentLiabilities,
                'total_non_current_liabilities_minor' => $totalNonCurrentLiabilities,
                'total_liabilities_minor' => $totalLiabilities,
                'total_equity_minor' => $totalEquityBase,
                'current_period_net_income_minor' => $currentPeriodNetIncome,
                'total_equity_including_net_income_minor' => $totalEquityIncludingNetIncome,
                'total_liabilities_and_equity_minor' => $totalLiabilitiesAndEquity,
                'is_balanced' => $isBalanced,
                'imbalance_minor' => $imbalanceMinor,
            ],
            'unmapped_accounts' => $unmappedAccountsData,
            'has_unmapped_warning' => count($unmappedAccountsData) > 0,
        ];
    }
}
