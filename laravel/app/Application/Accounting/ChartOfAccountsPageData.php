<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ChartOfAccountsPageData
{
    /**
     * @return array{
     *     groups: EloquentCollection<int, AccountGroup>,
     *     accounts: EloquentCollection<int, Account>,
     *     accountTypes: EloquentCollection<int, AccountType>,
     *     currencies: EloquentCollection<int, Currency>
     * }
     */
    public function indexData(): array
    {
        return [
            'groups' => AccountGroup::query()
                ->with(['accountType', 'children', 'accounts'])
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get(),
            'accounts' => Account::query()
                ->with(['accountType', 'group', 'currencyRef'])
                ->orderBy('code')
                ->get(),
            'accountTypes' => AccountType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
        ];
    }
}
