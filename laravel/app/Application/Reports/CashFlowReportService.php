<?php

namespace App\Application\Reports;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashFlowReportService
{
    /**
     * Generate the Cash Flow Statement report for the specified date range or financial period.
     *
     * @return array{
     *     from_date: string,
     *     to_date: string,
     *     period_id: string|null,
     *     opening_cash_minor: int,
     *     closing_cash_minor: int,
     *     period_cash_delta_minor: int,
     *     operating: array{
     *         inflows_minor: int,
     *         outflows_minor: int,
     *         net_minor: int
     *     },
     *     investing: array{
     *         inflows_minor: int,
     *         outflows_minor: int,
     *         net_minor: int
     *     },
     *     financing: array{
     *         inflows_minor: int,
     *         outflows_minor: int,
     *         net_minor: int
     *     },
     *     unclassified: array{
     *         inflows_minor: int,
     *         outflows_minor: int,
     *         net_minor: int
     *     },
     *     net_cash_change_minor: int,
     *     reconciled_closing_cash_minor: int,
     *     is_reconciled: bool,
     *     config_warnings: list<array{
     *         type: string,
     *         account_code: string
     *     }>,
     *     unclassified_warnings: list<array{
     *         journal_id: string,
     *         entry_number: string,
     *         entry_date: string,
     *         cash_net_minor: int,
     *         reason_code: string
     *     }>,
     *     has_config_warning: bool,
     *     has_unclassified_warning: bool
     * }
     */
    public function generate(?string $fromDate = null, ?string $toDate = null, ?string $periodId = null): array
    {
        if ($periodId) {
            $period = FinancialPeriod::query()->with('fiscalYear')->where('id', $periodId)->first();
            if ($period) {
                $fromDate = $period->start_date->format('Y-m-d');
                $toDate = $period->end_date->format('Y-m-d');
            }
        }

        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : Carbon::now()->startOfYear();
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::now()->endOfDay();

        $fromDateStr = $from->format('Y-m-d');
        $toDateStr = $to->format('Y-m-d');

        // Derive cash-equivalent GL accounts from active CashAccount & BankAccount records
        $configWarnings = [];
        $cashAccounts = CashAccount::query()->where('is_active', true)->get();
        $bankAccounts = BankAccount::query()->where('is_active', true)->get();

        $cashEquivalentGlIds = [];

        foreach ($cashAccounts as $ca) {
            if (! $ca->gl_account_id) {
                $configWarnings[] = [
                    'type' => 'cash_account_missing_gl',
                    'account_code' => (string) $ca->code,
                ];
            } else {
                $cashEquivalentGlIds[] = $ca->gl_account_id;
            }
        }

        foreach ($bankAccounts as $ba) {
            if (! $ba->gl_account_id) {
                $configWarnings[] = [
                    'type' => 'bank_account_missing_gl',
                    'account_code' => (string) $ba->code,
                ];
            } else {
                $cashEquivalentGlIds[] = $ba->gl_account_id;
            }
        }

        $cashEquivalentGlIds = array_unique(array_filter($cashEquivalentGlIds));

        // Calculate Opening Cash Balance (debit minus credit for entry_date < $fromDateStr)
        $openingCashRow = DB::table('ledger_entry')
            ->selectRaw('COALESCE(SUM(debit_minor), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) as total_credit')
            ->whereIn('account_id', $cashEquivalentGlIds)
            ->where('entry_date', '<', $fromDateStr)
            ->first();

        $openingCashMinor = (int) (($openingCashRow->total_debit ?? 0) - ($openingCashRow->total_credit ?? 0));

        // Calculate Closing Cash Balance (debit minus credit for entry_date <= $toDateStr)
        $closingCashRow = DB::table('ledger_entry')
            ->selectRaw('COALESCE(SUM(debit_minor), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit_minor), 0) as total_credit')
            ->whereIn('account_id', $cashEquivalentGlIds)
            ->where('entry_date', '<=', $toDateStr)
            ->first();

        $closingCashMinor = (int) (($closingCashRow->total_debit ?? 0) - ($closingCashRow->total_credit ?? 0));
        $periodCashDeltaMinor = $closingCashMinor - $openingCashMinor;

        // Find all cash journals in range [fromDateStr, toDateStr]
        $cashJournals = DB::table('ledger_entry')
            ->select('journal_entry_id')
            ->whereIn('account_id', $cashEquivalentGlIds)
            ->where('entry_date', '>=', $fromDateStr)
            ->where('entry_date', '<=', $toDateStr)
            ->groupBy('journal_entry_id')
            ->pluck('journal_entry_id')
            ->toArray();

        $operatingInflows = 0;
        $operatingOutflows = 0;

        $investingInflows = 0;
        $investingOutflows = 0;

        $financingInflows = 0;
        $financingOutflows = 0;

        $unclassifiedInflows = 0;
        $unclassifiedOutflows = 0;

        $unclassifiedWarnings = [];

        if (! empty($cashJournals)) {
            // Load all accounts referenced in these journals with their financial statement lines
            $allJournalEntries = DB::table('journal_entry')
                ->whereIn('id', $cashJournals)
                ->get()
                ->keyBy('id');

            $allLedgerEntries = DB::table('ledger_entry')
                ->whereIn('journal_entry_id', $cashJournals)
                ->get()
                ->groupBy('journal_entry_id');

            $allAccountIds = DB::table('ledger_entry')
                ->whereIn('journal_entry_id', $cashJournals)
                ->pluck('account_id')
                ->unique()
                ->toArray();

            $accounts = Account::query()
                ->whereIn('id', $allAccountIds)
                ->with('financialStatementLine')
                ->get()
                ->keyBy('id');

            foreach ($cashJournals as $journalId) {
                $journalObj = $allJournalEntries->get($journalId);
                $entries = $allLedgerEntries->get($journalId) ?? collect();

                // Compute net cash movement for this journal (cash debits minus cash credits)
                $cashNetMinor = 0;
                $nonCashLines = [];

                foreach ($entries as $entry) {
                    if (in_array($entry->account_id, $cashEquivalentGlIds, true)) {
                        $cashNetMinor += ((int) $entry->debit_minor - (int) $entry->credit_minor);
                    } else {
                        $nonCashLines[] = $entry;
                    }
                }

                // If cash net is zero and there are no non-cash lines, it's an internal cash transfer
                if ($cashNetMinor === 0 && empty($nonCashLines)) {
                    continue; // Internal cash transfer
                }

                // Resolve non-cash activities
                $resolvedActivities = [];
                $hasUnclassifiedLine = false;

                foreach ($nonCashLines as $nonCashLine) {
                    $acc = $accounts->get($nonCashLine->account_id);
                    $activity = null;
                    if ($acc) {
                        $activity = $acc->cash_flow_activity ?? $acc->financialStatementLine?->cash_flow_activity;
                    }

                    if (! $activity) {
                        $hasUnclassifiedLine = true;
                        $resolvedActivities[] = 'unclassified';
                    } else {
                        $resolvedActivities[] = $activity;
                    }
                }

                $uniqueActivities = array_unique($resolvedActivities);

                if (count($uniqueActivities) === 1 && ! $hasUnclassifiedLine) {
                    $assignedActivity = reset($uniqueActivities);
                } else {
                    $assignedActivity = 'unclassified';
                    $reasonCode = $hasUnclassifiedLine ? 'unclassified_non_cash_accounts' : 'mixed_cash_flow_activities';
                    $unclassifiedWarnings[] = [
                        'journal_id' => (string) $journalId,
                        'entry_number' => (string) ($journalObj->number ?? $journalId),
                        'entry_date' => (string) ($journalObj->entry_date ?? $fromDateStr),
                        'cash_net_minor' => $cashNetMinor,
                        'reason_code' => $reasonCode,
                    ];
                }

                // Aggregate by assigned activity
                if ($assignedActivity === 'operating') {
                    if ($cashNetMinor >= 0) {
                        $operatingInflows += $cashNetMinor;
                    } else {
                        $operatingOutflows += abs($cashNetMinor);
                    }
                } elseif ($assignedActivity === 'investing') {
                    if ($cashNetMinor >= 0) {
                        $investingInflows += $cashNetMinor;
                    } else {
                        $investingOutflows += abs($cashNetMinor);
                    }
                } elseif ($assignedActivity === 'financing') {
                    if ($cashNetMinor >= 0) {
                        $financingInflows += $cashNetMinor;
                    } else {
                        $financingOutflows += abs($cashNetMinor);
                    }
                } else {
                    if ($cashNetMinor >= 0) {
                        $unclassifiedInflows += $cashNetMinor;
                    } else {
                        $unclassifiedOutflows += abs($cashNetMinor);
                    }
                }
            }
        }

        $netOperatingMinor = $operatingInflows - $operatingOutflows;
        $netInvestingMinor = $investingInflows - $investingOutflows;
        $netFinancingMinor = $financingInflows - $financingOutflows;
        $netUnclassifiedMinor = $unclassifiedInflows - $unclassifiedOutflows;

        $netCashChangeMinor = $netOperatingMinor + $netInvestingMinor + $netFinancingMinor + $netUnclassifiedMinor;
        $reconciledClosingCashMinor = $openingCashMinor + $netCashChangeMinor;
        $isReconciled = ($reconciledClosingCashMinor === $closingCashMinor);

        return [
            'from_date' => $fromDateStr,
            'to_date' => $toDateStr,
            'period_id' => $periodId,
            'opening_cash_minor' => $openingCashMinor,
            'closing_cash_minor' => $closingCashMinor,
            'period_cash_delta_minor' => $periodCashDeltaMinor,
            'operating' => [
                'inflows_minor' => $operatingInflows,
                'outflows_minor' => $operatingOutflows,
                'net_minor' => $netOperatingMinor,
            ],
            'investing' => [
                'inflows_minor' => $investingInflows,
                'outflows_minor' => $investingOutflows,
                'net_minor' => $netInvestingMinor,
            ],
            'financing' => [
                'inflows_minor' => $financingInflows,
                'outflows_minor' => $financingOutflows,
                'net_minor' => $netFinancingMinor,
            ],
            'unclassified' => [
                'inflows_minor' => $unclassifiedInflows,
                'outflows_minor' => $unclassifiedOutflows,
                'net_minor' => $netUnclassifiedMinor,
            ],
            'net_cash_change_minor' => $netCashChangeMinor,
            'reconciled_closing_cash_minor' => $reconciledClosingCashMinor,
            'is_reconciled' => $isReconciled,
            'config_warnings' => $configWarnings,
            'unclassified_warnings' => $unclassifiedWarnings,
            'has_config_warning' => count($configWarnings) > 0,
            'has_unclassified_warning' => count($unclassifiedWarnings) > 0,
        ];
    }
}
