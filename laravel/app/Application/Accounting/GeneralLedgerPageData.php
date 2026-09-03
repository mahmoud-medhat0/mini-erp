<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class GeneralLedgerPageData
{
    public function __construct(private readonly GeneralLedgerService $generalLedgerService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $totals = $this->generalLedgerService->getGeneralLedgerTotals($filters);

        return [
            'totals' => [
                'debit' => $totals['total_debit'],
                'credit' => $totals['total_credit'],
                'net' => $totals['net_movement'],
            ],
            'accounts' => Account::query()->orderBy('code')->get(),
            'branches' => Branch::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->orderBy('start_date', 'desc')->get(),
            'filters' => $filters,
            'displayCurrency' => $this->displayCurrency(),
        ];
    }

    /**
     * Server-side DataTables feed for the general ledger grid.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $accountId = (string) ($filters['account_id'] ?? '');
        $periodId = (string) ($filters['period_id'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');
        $startDate = (string) ($filters['start_date'] ?? '');
        $endDate = (string) ($filters['end_date'] ?? '');

        $query = LedgerEntry::query()
            ->with(['account:id,code,name', 'branch:id,code,name', 'journalEntry:id,number'])
            ->when($accountId !== '', fn ($q) => $q->where('account_id', $accountId))
            ->when($periodId !== '', fn ($q) => $q->where('financial_period_id', $periodId))
            ->when($branchId !== '', fn ($q) => $q->where('branch_id', $branchId))
            ->when($startDate !== '', fn ($q) => $q->where('entry_date', '>=', $startDate))
            ->when($endDate !== '', fn ($q) => $q->where('entry_date', '<=', $endDate))
            ->orderBy('entry_date', 'asc')
            ->orderBy('created_at', 'asc');

        return DataTables::eloquent($query)
            ->filterColumn('account_code', function ($q, $keyword): void {
                $needle = '%'.$keyword.'%';
                $q->whereHas('account', fn ($acc) => $acc->where('code', 'like', $needle)->orWhere('name', 'like', $needle));
            })
            ->filterColumn('voucher_number', function ($q, $keyword): void {
                $needle = '%'.$keyword.'%';
                $q->whereHas('journalEntry', fn ($je) => $je->where('number', 'like', $needle));
            })
            ->addColumn('account_code', fn ($row) => $row->account?->code)
            ->addColumn('account_name', fn ($row) => $row->account?->name)
            ->addColumn('branch_code', fn ($row) => $row->branch?->code)
            ->addColumn('branch_name', fn ($row) => $row->branch?->name)
            ->addColumn('voucher_number', fn ($row) => $row->journalEntry?->number)
            ->addColumn('journal_entry_id', fn ($row) => $row->journalEntry?->id)
            ->toJson();
    }

    private function displayCurrency(): string
    {
        return (string) (
            Company::query()->orderBy('created_at')->value('base_currency')
            ?: Account::query()->whereNotNull('currency')->orderBy('code')->value('currency')
            ?: Currency::query()->orderBy('code')->value('code')
            ?: config('erp_currencies.default')
        );
    }
}
