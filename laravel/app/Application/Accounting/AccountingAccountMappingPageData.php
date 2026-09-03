<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class AccountingAccountMappingPageData
{
    /**
     * @return array{
     *     mappingKeys: array<int, string>,
     *     mappings: Collection<int, AccountingAccountMapping>,
     *     accounts: Collection<int, Account>,
     *     branches: Collection<int, Branch>
     * }
     */
    public function indexData(): array
    {
        return [
            'mappingKeys' => AccountingAccountMappingService::ALLOWED_KEYS,
            'mappings' => AccountingAccountMapping::query()
                ->orderBy('key')
                ->orderBy('branch_id')
                ->get(['id', 'key', 'branch_id'])
                ->values(),
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'nature', 'currency', 'is_control', 'allow_manual_posting']),
            'branches' => Branch::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'is_active']),
        ];
    }

    /**
     * Server-side DataTables feed for the mappings grid.
     *
     * The nested `account` and `branch` objects are kept intact because the
     * client composes its delete confirmation from them.
     *
     * @param  array<string, mixed>  $filters
     */
    public function datatable(array $filters = []): JsonResponse
    {
        $scope = (string) ($filters['scope'] ?? '');
        $key = (string) ($filters['key'] ?? '');

        $query = AccountingAccountMapping::query()
            ->with([
                'account:id,code,name,type,nature,currency,is_active',
                'branch:id,code,name,is_active',
            ])
            ->leftJoin('account', 'account.id', '=', 'accounting_account_mapping.account_id')
            ->leftJoin('branch', 'branch.id', '=', 'accounting_account_mapping.branch_id')
            ->select([
                'accounting_account_mapping.id',
                'accounting_account_mapping.key',
                'accounting_account_mapping.branch_id',
                'accounting_account_mapping.account_id',
                'accounting_account_mapping.description',
                'accounting_account_mapping.is_system',
            ])
            ->when($scope === 'global', fn ($q) => $q->whereNull('accounting_account_mapping.branch_id'))
            ->when($scope === 'branch', fn ($q) => $q->whereNotNull('accounting_account_mapping.branch_id'))
            ->when(
                $key !== '' && in_array($key, AccountingAccountMappingService::ALLOWED_KEYS, true),
                fn ($q) => $q->where('accounting_account_mapping.key', $key),
            )
            ->orderBy('accounting_account_mapping.key')
            ->orderBy('accounting_account_mapping.branch_id');

        return DataTables::eloquent($query)
            ->filterColumn('key', function ($q, $keyword): void {
                $needle = '%'.mb_strtolower($keyword).'%';
                $q->where(function ($inner) use ($needle): void {
                    $inner->whereRaw('LOWER(accounting_account_mapping.key) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(account.code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(account.name AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(branch.code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(accounting_account_mapping.description) LIKE ?', [$needle]);
                });
            })
            // `id`, `key` and `description` also exist on the joined tables.
            ->orderColumn('key', 'accounting_account_mapping.key $1')
            ->orderColumn('scope', 'branch.code $1')
            ->orderColumn('account', 'account.code $1')
            ->orderColumn('description', 'accounting_account_mapping.description $1')
            ->addColumn('scope', fn ($row) => $row->branch_id ? 'branch' : 'global')
            ->toJson();
    }
}
