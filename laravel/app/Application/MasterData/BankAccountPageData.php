<?php

namespace App\Application\MasterData;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class BankAccountPageData
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
            'bankAccounts' => [],
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

        $query = BankAccount::query()
            ->with(['glAccount', 'branch'])
            ->select('bank_account.*')
            ->when(in_array($status, ['active', 'inactive'], true), fn ($builder) => $builder->where('bank_account.is_active', $status === 'active'))
            ->when($branchId !== '', fn ($builder) => $builder->where('bank_account.branch_id', $branchId));

        return DataTables::eloquent($query)
            ->addColumn('bank_label', fn (BankAccount $account): string => trim((string) $account->bank_name.' - '.(string) $account->name, ' -'))
            ->addColumn('branch_label', fn (): string => '')
            ->addColumn('gl_account_label', fn (): string => '')
            ->addColumn('actions', fn (): string => '')
            ->filterColumn('bank_label', function ($builder, string $keyword): void {
                $builder->where(function ($inner) use ($keyword): void {
                    $inner->where('bank_account.name->en', 'like', "%{$keyword}%")
                        ->orWhere('bank_account.name->ar', 'like', "%{$keyword}%")
                        ->orWhere('bank_account.bank_name->en', 'like', "%{$keyword}%")
                        ->orWhere('bank_account.bank_name->ar', 'like', "%{$keyword}%");
                });
            })
            ->toJson();
    }
}
