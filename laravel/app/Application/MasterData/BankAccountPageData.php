<?php

namespace App\Application\MasterData;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Currency;

class BankAccountPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $branchId = $filters['branch_id'] ?? null;

        $query = BankAccount::query()->with(['glAccount', 'branch']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'bankAccounts' => $query->orderBy('code', 'asc')
                ->paginate(15)
                ->withQueryString(),
            'glAccounts' => Account::query()->where('is_active', true)->where('type', 'asset')->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
        ];
    }
}
