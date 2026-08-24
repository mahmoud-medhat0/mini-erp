<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\AuthorizesAccountingRequests;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingOverviewController extends Controller
{
    use AuthorizesAccountingRequests;

    public function __invoke(Request $request): Response
    {
        $this->authorizePermission($request, 'accounting.view');

        return Inertia::render('Accounting/Index', [
            'activeFiscalYear' => FiscalYear::query()->where('status', 'open')->orderBy('year', 'desc')->first(),
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
        ]);
    }
}
