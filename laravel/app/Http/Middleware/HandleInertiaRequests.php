<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email', 'locale', 'theme'),
                'permissions' => fn () => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->sort()->values()->all()
                    : [],
            ],
            'locale' => app()->getLocale(),
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'theme' => $request->user()?->theme ?? $request->session()->get('theme', 'system'),
            'notifications' => [
                'unreadCount' => fn () => $request->user() && Schema::hasTable('notification')
                    ? DB::table('notification')
                        ->where('user_id', $request->user()->id)
                        ->where('read', false)
                        ->count()
                    : 0,
                'recent' => fn () => $request->user() && Schema::hasTable('notification')
                    ? DB::table('notification')
                        ->where('notification.user_id', $request->user()->id)
                        ->select([
                            'notification.id',
                            'notification.type',
                            'notification.target_ref',
                            'notification.read',
                            'notification.at',
                        ])
                        ->orderByDesc('notification.at')
                        ->limit(5)
                        ->get()
                        ->map(fn (object $n): array => [
                            'id' => $n->id,
                            'type' => $n->type,
                            'targetRef' => $n->target_ref,
                            'read' => (bool) $n->read,
                            'at' => $n->at === null ? null : (string) $n->at,
                        ])
                        ->all()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
