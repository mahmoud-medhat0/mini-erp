<?php

namespace App\Http\Controllers;

use App\Application\Notifications\NotificationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Yajra\DataTables\Facades\DataTables;

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

    public function data(Request $request, NotificationService $notifications): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'string', Rule::in(['all', 'unread', 'read'])],
        ]);
        $tab = $validated['tab'] ?? 'all';
        $query = $notifications
            ->queryForUser((int) $request->user()->getAuthIdentifier())
            ->when($tab === 'unread', fn (Builder $builder) => $builder->where('notification.read', false))
            ->when($tab === 'read', fn (Builder $builder) => $builder->where('notification.read', true))
            ->select([
                'notification.id',
                'notification.type',
                'notification.target_ref',
                'notification.read',
                'notification.at',
            ]);

        return DataTables::query($query)
            ->filter(function (Builder $builder): void {
                $search = trim((string) request()->input('search.value', ''));

                if ($search === '') {
                    return;
                }

                $like = '%'.mb_strtolower($search).'%';
                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw("LOWER(COALESCE(notification.type, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(notification.target_ref, '')) LIKE ?", [$like]);
                });
            })
            ->orderColumn('type', 'notification.type $1')
            ->orderColumn('target_ref', 'notification.target_ref $1')
            ->orderColumn('read', 'notification.read $1')
            ->orderColumn('at', 'notification.at $1')
            ->editColumn('id', fn (object $notification): string => (string) $notification->id)
            ->editColumn('read', fn (object $notification): bool => (bool) $notification->read)
            ->addColumn('actions', fn (): null => null)
            ->toJson();
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
