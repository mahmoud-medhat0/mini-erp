<?php

namespace App\Application\Accounting;

use App\Models\AccountCategory;
use App\Models\AccountType;
use Illuminate\Support\Collection;

class AccountTypePageData
{
    /**
     * @return array{
     *     accountTypes: Collection<int, AccountType>,
     *     accountCategories: Collection<int, AccountCategory>
     * }
     */
    public function indexData(): array
    {
        return [
            'accountTypes' => AccountType::query()
                ->with(['accountCategory', 'groups', 'accounts'])
                ->withCount(['groups', 'accounts'])
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'accountCategories' => AccountCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
        ];
    }
}
