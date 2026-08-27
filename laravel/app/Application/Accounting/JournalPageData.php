<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;

class JournalPageData
{
    public function __construct(private readonly GeneralLedgerService $generalLedgerService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     journals: LengthAwarePaginator,
     *     periods: EloquentCollection<int, FinancialPeriod>,
     *     branches: EloquentCollection<int, Branch>,
     *     filters: array<string, mixed>
     * }
     */
    public function indexData(array $filters): array
    {
        return [
            'journals' => $this->generalLedgerService->getGeneralJournal($filters),
            'periods' => FinancialPeriod::query()->with('fiscalYear')->orderBy('start_date', 'desc')->get(),
            'branches' => Branch::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'filters' => Arr::only($filters, ['status', 'period_id', 'start_date', 'end_date', 'branch_id']),
        ];
    }

    /**
     * @return array{
     *     periods: EloquentCollection<int, FinancialPeriod>,
     *     accounts: EloquentCollection<int, Account>,
     *     currencies: EloquentCollection<int, Currency>,
     *     branches: EloquentCollection<int, Branch>
     * }
     */
    public function createData(): array
    {
        return [
            'periods' => $this->openPeriods(),
            'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
        ];
    }

    /**
     * @return array{
     *     journal: JournalEntry,
     *     openPeriods: EloquentCollection<int, FinancialPeriod>
     * }
     */
    public function showData(JournalEntry $journalEntry): array
    {
        $journalEntry->load(['branch', 'lines.account', 'lines.branch', 'period.fiscalYear', 'currencyRef', 'createdBy', 'postedBy', 'reversesEntry', 'reversalEntry']);

        return [
            'journal' => $journalEntry,
            'openPeriods' => $this->openPeriods(),
        ];
    }

    /**
     * @return EloquentCollection<int, FinancialPeriod>
     */
    private function openPeriods(): EloquentCollection
    {
        return FinancialPeriod::query()
            ->with('fiscalYear')
            ->whereIn('status', ['open', 'reopened'])
            ->orderBy('start_date', 'desc')
            ->get();
    }
}
