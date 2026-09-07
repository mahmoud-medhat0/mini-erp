<?php

namespace App\Application\MasterData;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class CashAccountPageData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');

        return [
            'cashAccounts' => [],
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

    /**
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters): JsonResponse
    {
        $status = (string) ($filters['status'] ?? '');
        $branchId = (string) ($filters['branch_id'] ?? '');

        $query = CashAccount::query()
            ->with(['glAccount', 'branch'])
            ->select('cash_account.*')
            ->when(in_array($status, ['active', 'inactive'], true), fn ($builder) => $builder->where('cash_account.is_active', $status === 'active'))
            ->when($branchId !== '', fn ($builder) => $builder->where('cash_account.branch_id', $branchId));

        return DataTables::eloquent($query)
            ->addColumn('name_text', fn (CashAccount $account): string => (string) $account->name)
            ->addColumn('branch_label', fn (): string => '')
            ->addColumn('gl_account_label', fn (): string => '')
            ->addColumn('actions', fn (): string => '')
            ->filterColumn('name_text', function ($builder, string $keyword): void {
                $builder->where(function ($inner) use ($keyword): void {
                    $inner->where('cash_account.name->en', 'like', "%{$keyword}%")
                        ->orWhere('cash_account.name->ar', 'like', "%{$keyword}%");
                });
            })
            ->toJson();
    }
}
