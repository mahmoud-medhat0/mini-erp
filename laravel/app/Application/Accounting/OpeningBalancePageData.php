<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\OpeningBalance;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class OpeningBalancePageData
{
    /**
     * @return array{
     *     fiscalYears: EloquentCollection<int, FiscalYear>,
     *     selectedYearId: mixed,
     *     accounts: EloquentCollection<int, Account>,
     *     existingBalances: array<string, mixed>
     * }
     */
    public function indexData(mixed $requestedFiscalYearId): array
    {
        $fiscalYears = FiscalYear::query()->orderBy('year', 'desc')->get();
        $selectedYearId = $requestedFiscalYearId ?? $fiscalYears->first()?->id;

        return [
            'fiscalYears' => $fiscalYears,
            'selectedYearId' => $selectedYearId,
            'accounts' => Account::query()->with('group')->orderBy('code')->get(),
            'existingBalances' => $this->existingBalances($selectedYearId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function existingBalances(mixed $fiscalYearId): array
    {
        if (! $fiscalYearId) {
            return [];
        }

        return OpeningBalance::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->get()
            ->keyBy('account_id')
            ->toArray();
    }
}
