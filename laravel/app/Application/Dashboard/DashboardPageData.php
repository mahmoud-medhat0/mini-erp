<?php

namespace App\Application\Dashboard;

use App\Application\Notifications\NotificationService;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class DashboardPageData
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(int|string $userId): array
    {
        return [
            'counts' => [
                'accounts' => Account::query()->count(),
                'postedJournals' => JournalEntry::query()->where('status', 'posted')->count(),
                'ledgerEntries' => LedgerEntry::query()->count(),
                'currencies' => Currency::query()->count(),
                'customers' => Customer::query()->count(),
                'suppliers' => Supplier::query()->count(),
                'unreadNotifications' => $this->notificationService->unreadCount($userId),
            ],
            'recentNotifications' => $this->recentNotifications($userId),
        ];
    }

    /**
     * @return Collection<int, array{id: string, type: string, targetRef: string|null, read: bool, at: string|null}>
     */
    private function recentNotifications(int|string $userId): Collection
    {
        return $this->notificationService
            ->queryForUser($userId)
            ->orderByDesc('at')
            ->limit(5)
            ->get()
            ->map(fn (object $item): array => [
                'id' => (string) $item->id,
                'type' => (string) $item->type,
                'targetRef' => $item->target_ref === null ? null : (string) $item->target_ref,
                'read' => (bool) $item->read,
                'at' => $item->at === null ? null : (string) $item->at,
            ])
            ->values();
    }
}
