<?php

namespace App\Http\Controllers;

use App\Application\Notifications\NotificationService;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class AppPageController extends Controller
{
    public function foundation(): Response
    {
        return Inertia::render('Foundation', [
            'status' => 'M6 page migration',
            'database' => 'not_checked',
        ]);
    }

    public function dashboard(): Response
    {
        $userId = auth()->id();

        return Inertia::render('Dashboard', [
            'counts' => [
                'companies' => Company::query()->count(),
                'branches' => Branch::query()->count(),
                'users' => User::query()->count(),
                'roles' => Role::query()->count(),
                'permissions' => Permission::query()->count(),
                'numberSequences' => DB::table('number_sequence')->count(),
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

    public function settings(): Response
    {
        return Inertia::render('Settings/Index');
    }

    public function companies(Request $request): Response
    {
        $locale = $this->locale($request);

        return Inertia::render('Settings/Company', [
            'currencies' => collect(config('erp_currencies.supported'))
                ->map(fn (array $currency): array => [
                    'code' => $currency['code'],
                    'name' => $currency['name'][$locale] ?? $currency['name']['en'] ?? $currency['code'],
                ])
                ->values(),
            'companies' => Company::query()
                ->orderBy('created_at')
                ->get()
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'name' => $this->modelTranslation($company, 'name', $locale),
                    'nameEn' => $this->modelTranslation($company, 'name', 'en'),
                    'nameAr' => $this->modelTranslation($company, 'name', 'ar'),
                    'baseCurrency' => $company->base_currency,
                    'lockVersion' => (int) $company->lock_version,
                    'createdAt' => optional($company->created_at)->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function branches(Request $request): Response
    {
        $locale = $this->locale($request);

        return Inertia::render('Settings/Branches', [
            'branches' => Branch::query()
                ->orderBy('code')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $this->modelTranslation($branch, 'name', $locale),
                    'nameEn' => $this->modelTranslation($branch, 'name', 'en'),
                    'nameAr' => $this->modelTranslation($branch, 'name', 'ar'),
                    'isActive' => $branch->is_active,
                    'lockVersion' => (int) $branch->lock_version,
                ])
                ->values(),
        ]);
    }

    public function numbering(Request $request): Response
    {
        return Inertia::render('Settings/Numbering', [
            'sequences' => DB::table('number_sequence')
                ->select([
                    'number_sequence.id',
                    'number_sequence.key',
                    'number_sequence.doc_type',
                    'number_sequence.prefix',
                    'number_sequence.include_year',
                    'number_sequence.padding',
                    'number_sequence.reset_policy',
                    'number_sequence.next_value',
                ])
                ->orderBy('number_sequence.doc_type')
                ->get()
                ->map(fn (object $sequence): array => [
                    'id' => $sequence->id,
                    'key' => $sequence->key,
                    'docType' => $sequence->doc_type,
                    'prefix' => $sequence->prefix,
                    'includeYear' => (bool) $sequence->include_year,
                    'padding' => (int) $sequence->padding,
                    'resetPolicy' => $sequence->reset_policy,
                    'nextValue' => (int) $sequence->next_value,
                    'preview' => $this->previewNumber($sequence),
                ])
                ->values(),
        ]);
    }

    public function users(): Response
    {
        return Inertia::render('Settings/Users', [
            'users' => User::query()
                ->with('roles')
                ->orderBy('email')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'theme' => $user->theme,
                    'isActive' => $user->is_active,
                    'roles' => $user->roles
                        ->sortBy('name')
                        ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
                        ->values(),
                ])
                ->values(),
            'roles' => Role::query()
                ->with(['permissions' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'isTemplate' => (bool) $role->is_template,
                    'permissions' => $role->permissions
                        ->pluck('name')
                        ->values(),
                ])
                ->values(),
            'allPermissions' => Permission::query()
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    public function notifications(Request $request, NotificationService $notifications): Response
    {
        return Inertia::render('Notifications', [
            'items' => $notifications->queryForUser($request->user()->id)
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

    public function markNotificationRead(Request $request, string $id): RedirectResponse
    {
        app(NotificationService::class)->markRead($request->user()->id, $id);

        return back()->with('success', __('Notification marked as read.'));
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        app(NotificationService::class)->markAllRead($request->user()->id);

        return back()->with('success', __('All notifications marked as read.'));
    }

    private function locale(Request $request): string
    {
        return $request->user()?->locale === 'ar' || app()->getLocale() === 'ar' ? 'ar' : 'en';
    }

    private function modelTranslation(object $model, string $field, string $locale): string
    {
        if (method_exists($model, 'getTranslation')) {
            try {
                $value = $model->getTranslation($field, $locale, false);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            } catch (Throwable) {
                //
            }
        }

        return $this->translationFromJson($model->{$field} ?? null, $locale);
    }

    private function translationFromJson(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            return (string) ($value[$locale] ?? $value['en'] ?? reset($value) ?: '');
        }

        if (! is_string($value) || $value === '') {
            return '';
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return (string) ($decoded[$locale] ?? $decoded['en'] ?? reset($decoded) ?: '');
        }

        return $value;
    }

    private function previewNumber(object $sequence): string
    {
        $parts = array_filter([
            (string) $sequence->prefix,
            (bool) $sequence->include_year ? now()->year : null,
            str_pad((string) max(1, (int) $sequence->next_value), (int) $sequence->padding, '0', STR_PAD_LEFT),
        ], static fn ($part): bool => $part !== null && $part !== '');

        return implode('-', $parts);
    }
}
