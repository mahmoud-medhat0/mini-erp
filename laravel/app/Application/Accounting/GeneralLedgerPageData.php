<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GeneralLedgerPageData
{
    public function __construct(private readonly GeneralLedgerService $generalLedgerService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $ledgerData = $this->generalLedgerService->getGeneralLedger($filters);
        $entries = $ledgerData['entries'];

        return [
            'ledger' => $entries,
            'totals' => [
                'debit' => $ledgerData['total_debit'],
                'credit' => $ledgerData['total_credit'],
                'net' => $ledgerData['net_movement'],
            ],
            'accounts' => Account::query()->orderBy('code')->get(),
            'branches' => Branch::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->orderBy('start_date', 'desc')->get(),
            'filters' => $filters,
            'displayCurrency' => $this->displayCurrency($entries),
        ];
    }

    private function displayCurrency(LengthAwarePaginator $entries): string
    {
        $firstEntry = $entries->getCollection()->first();

        if ($firstEntry?->currency) {
            return (string) $firstEntry->currency;
        }

        return (string) (
            Company::query()->orderBy('created_at')->value('base_currency')
            ?: Account::query()->whereNotNull('currency')->orderBy('code')->value('currency')
            ?: Currency::query()->orderBy('code')->value('code')
            ?: config('erp_currencies.default')
        );
    }
}
