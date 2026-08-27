<?php

namespace App\Application\Accounting;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;

class AccountingOverviewPageData
{
    /**
     * @return array{
     *     activeFiscalYear: FiscalYear|null,
     *     recentJournals: Collection<int, JournalEntry>,
     *     counts: array{accounts: int, postedJournals: int, draftJournals: int}
     * }
     */
    public function indexData(): array
    {
        return [
            'activeFiscalYear' => FiscalYear::query()
                ->where('status', 'open')
                ->orderBy('year', 'desc')
                ->first(),
            'recentJournals' => JournalEntry::query()
                ->with(['period', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(),
            'counts' => [
                'accounts' => Account::query()->count(),
                'postedJournals' => JournalEntry::query()->where('status', 'posted')->count(),
                'draftJournals' => JournalEntry::query()->where('status', 'draft')->count(),
            ],
        ];
    }
}
