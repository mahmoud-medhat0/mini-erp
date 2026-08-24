<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $userId = auth()->id();

        return Inertia::render('Dashboard', [
            'counts' => [
                'accounts' => Account::query()->count(),
                'postedJournals' => JournalEntry::query()->where('status', 'posted')->count(),
                'ledgerEntries' => LedgerEntry::query()->count(),
                'currencies' => Currency::query()->count(),
                'customers' => Customer::query()->count(),
                'suppliers' => Supplier::query()->count(),
                'unreadNotifications' => DB::table('notification')
                    ->where('user_id', $userId)
                    ->where('read', false)
                    ->count(),
            ],
            'recentNotifications' => DB::table('notification')
                ->where('user_id', $userId)
                ->orderByDesc('at')
                ->limit(5)
                ->get()
                ->map(fn (object $item): array => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'targetRef' => $item->target_ref,
                    'read' => (bool) $item->read,
                    'at' => $item->at,
                ])
                ->values(),
        ]);
    }
}
