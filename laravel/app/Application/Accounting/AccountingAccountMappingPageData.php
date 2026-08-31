<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Branch;
use Illuminate\Support\Collection;

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
                ->with(['account:id,code,name,type,nature,currency,is_active', 'branch:id,code,name,is_active'])
                ->orderBy('key')
                ->orderBy('branch_id')
                ->get(['id', 'key', 'branch_id', 'account_id', 'description', 'is_system'])
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
}
