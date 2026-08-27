<?php

namespace App\Application\Accounting;

use App\Models\AccountCategory;
use Illuminate\Support\Collection;

class AccountCategoryPageData
{
    /**
     * @return array{accountCategories: Collection<int, AccountCategory>}
     */
    public function indexData(): array
    {
        return [
            'accountCategories' => AccountCategory::query()
                ->with(['accountTypes'])
                ->withCount('accountTypes')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
        ];
    }
}
