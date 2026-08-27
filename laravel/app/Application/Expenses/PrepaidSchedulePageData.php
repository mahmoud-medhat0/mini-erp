<?php

namespace App\Application\Expenses;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\PrepaidSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PrepaidSchedulePageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     schedules: LengthAwarePaginator,
     *     categories: EloquentCollection<int, ExpenseCategory>,
     *     prepaidAssetAccounts: EloquentCollection<int, Account>,
     *     expenseAccounts: EloquentCollection<int, Account>,
     *     branches: EloquentCollection<int, Branch>,
     *     currencies: EloquentCollection<int, Currency>,
     *     statuses: array<int, string>,
     *     filters: array{search: string, status: string, branch_id: string}
     * }
     */
    public function indexData(array $filters): array
    {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));
        $branchId = (string) ($filters['branch_id'] ?? '');

        $schedules = PrepaidSchedule::query()
            ->with(['branch', 'category', 'prepaidAssetAccount', 'expenseAccount', 'recognitions.period', 'recognitions.journalEntry'])
            ->when($status !== '' && in_array($status, PrepaidScheduleService::ALLOWED_STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('schedule_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return [
            'schedules' => $schedules,
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'prepaidAssetAccounts' => $this->prepaidAssetAccounts(),
            'expenseAccounts' => $this->expenseAccounts(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['code', 'name', 'symbol']),
            'statuses' => PrepaidScheduleService::ALLOWED_STATUSES,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
        ];
    }

    /**
     * @return EloquentCollection<int, Account>
     */
    private function prepaidAssetAccounts(): EloquentCollection
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where('nature', 'debit')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'currency']);
    }

    /**
     * @return EloquentCollection<int, Account>
     */
    private function expenseAccounts(): EloquentCollection
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'expense')
            ->where('nature', 'debit')
            ->where('is_control', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'currency']);
    }
}
