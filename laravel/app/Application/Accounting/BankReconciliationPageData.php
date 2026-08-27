<?php

namespace App\Application\Accounting;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BankReconciliationPageData
{
    public function __construct(private readonly BankReconciliationService $service) {}

    /**
     * @param  array{status?: mixed, bank_account_id?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = [
            'status' => $filters['status'] ?? null,
            'bank_account_id' => $filters['bank_account_id'] ?? null,
        ];

        return [
            'reconciliations' => $this->reconciliations($normalizedFilters),
            'bankAccounts' => $this->activeBankAccounts(),
            'periods' => $this->openPeriods(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(string $id): array
    {
        return [
            'reconciliation' => BankReconciliation::query()
                ->with(['bankAccount', 'financialPeriod', 'lines.matchedLedgerEntry.journalEntry'])
                ->findOrFail($id),
            'summary' => $this->service->summary($id),
            'candidates' => $this->service->candidateLedgerEntries($id),
        ];
    }

    /**
     * @param  array{status: mixed, bank_account_id: mixed}  $filters
     */
    private function reconciliations(array $filters): LengthAwarePaginator
    {
        return BankReconciliation::query()
            ->with(['bankAccount', 'financialPeriod'])
            ->when($filters['status'], fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['bank_account_id'], fn ($query) => $query->where('bank_account_id', $filters['bank_account_id']))
            ->orderBy('date_from', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return Collection<int, BankAccount>
     */
    private function activeBankAccounts(): Collection
    {
        return BankAccount::query()->where('is_active', true)->orderBy('code')->get();
    }

    /**
     * @return Collection<int, FinancialPeriod>
     */
    private function openPeriods(): Collection
    {
        return FinancialPeriod::query()->where('is_closed', false)->orderBy('start_date', 'asc')->get();
    }
}
