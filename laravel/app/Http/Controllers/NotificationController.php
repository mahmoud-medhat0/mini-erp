<?php

namespace App\Http\Controllers;

use App\Application\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notifications): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $validated = $request->validate([
            'tab' => ['nullable', 'string', Rule::in(['all', 'unread', 'read'])],
        ]);
        $tab = $validated['tab'] ?? 'all';
        $baseQuery = $notifications->queryForUser($userId);
        $items = (clone $baseQuery)
            ->when($tab === 'unread', fn ($query) => $query->where('read', false))
            ->when($tab === 'read', fn ($query) => $query->where('read', true))
            ->select([
                'notification.id',
                'notification.type',
                'notification.target_ref',
                'notification.read',
                'notification.at',
            ])
            ->orderByDesc('notification.at')
            ->paginate(25)
            ->withQueryString();

        $items->getCollection()->transform(fn (object $notification): array => [
            'id' => $notification->id,
            'type' => $notification->type,
            'targetRef' => $notification->target_ref,
            'read' => (bool) $notification->read,
            'at' => $notification->at,
        ]);
        $total = (clone $baseQuery)->count();
        $unread = (clone $baseQuery)->where('read', false)->count();

        return Inertia::render('Notifications', [
            'items' => $items,
            'counts' => [
                'all' => $total,
                'unread' => $unread,
                'read' => $total - $unread,
            ],
            'filters' => ['tab' => $tab],
        ]);
    }

    public function markRead(Request $request, string $id, NotificationService $notifications): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $notifications->markRead($userId, $id);

        return back()->with('success', __('Notification marked as read.'));
    }

    public function markAllRead(Request $request, NotificationService $notifications): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $notifications->markAllRead($userId);

        return back()->with('success', __('All notifications marked as read.'));
    }
}
