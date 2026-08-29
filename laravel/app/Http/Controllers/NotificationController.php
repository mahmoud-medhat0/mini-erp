<?php

namespace App\Http\Controllers;

use App\Application\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notifications): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        return Inertia::render('Notifications', [
            'items' => $notifications->queryForUser($userId)
                ->select([
                    'notification.id',
                    'notification.type',
                    'notification.target_ref',
                    'notification.read',
                    'notification.at',
                ])
                ->orderByDesc('notification.at')
                ->limit(100)
                ->get()
                ->map(fn (object $notification): array => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'targetRef' => $notification->target_ref,
                    'read' => (bool) $notification->read,
                    'at' => $notification->at,
                ])
                ->values(),
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
