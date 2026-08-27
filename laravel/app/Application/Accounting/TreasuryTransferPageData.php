<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\TreasuryTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class TreasuryTransferPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     transfers: LengthAwarePaginator,
     *     cashAccounts: EloquentCollection<int, CashAccount>,
     *     bankAccounts: EloquentCollection<int, BankAccount>,
     *     fiscalYears: EloquentCollection<int, FiscalYear>,
     *     financialPeriods: EloquentCollection<int, FinancialPeriod>,
     *     statuses: array<int, string>,
     *     filters: array{search: mixed, status: mixed}
     * }
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
        ];

        return [
            'transfers' => $this->transfers($normalizedFilters),
            'cashAccounts' => $this->activeCashAccounts(),
            'bankAccounts' => $this->activeBankAccounts(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('year')->get(['id', 'year', 'status']),
            'financialPeriods' => FinancialPeriod::query()->orderBy('start_date')->get(['id', 'fiscal_year_id', 'month', 'start_date', 'end_date', 'status']),
            'statuses' => TreasuryTransferService::ALLOWED_STATUSES,
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array{search: mixed, status: mixed}  $filters
     */
    private function transfers(array $filters): LengthAwarePaginator
    {
        return TreasuryTransfer::query()
            ->with([
                'sourceCashAccount.branch',
                'sourceBankAccount.branch',
                'destinationCashAccount.branch',
                'destinationBankAccount.branch',
                'sourceBranch',
                'destinationBranch',
                'journalEntry',
            ])
            ->when($filters['search'], function (Builder $query) use ($filters): void {
                $query->where(function (Builder $inner) use ($filters): void {
                    $inner->where('number', 'like', "%{$filters['search']}%")
                        ->orWhere('reference', 'like', "%{$filters['search']}%")
                        ->orWhere('description', 'like', "%{$filters['search']}%");
                });
            })
            ->when(
                $filters['status'] && in_array($filters['status'], TreasuryTransferService::ALLOWED_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->orderBy('transfer_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return EloquentCollection<int, CashAccount>
     */
    private function activeCashAccounts(): EloquentCollection
    {
        return CashAccount::query()
            ->where('is_active', true)
            ->with(['branch', 'glAccount'])
            ->orderBy('code')
            ->get();
    }

    /**
     * @return EloquentCollection<int, BankAccount>
     */
    private function activeBankAccounts(): EloquentCollection
    {
        return BankAccount::query()
            ->where('is_active', true)
            ->with(['branch', 'glAccount'])
            ->orderBy('code')
            ->get();
    }
}
