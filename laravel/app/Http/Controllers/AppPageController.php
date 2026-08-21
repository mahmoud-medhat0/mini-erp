<?php

namespace App\Http\Controllers;

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
        return Inertia::render('Dashboard', [
            'counts' => [
                'companies' => Company::query()->count(),
                'branches' => Branch::query()->count(),
                'users' => User::query()->count(),
                'roles' => Role::query()->count(),
                'permissions' => Permission::query()->count(),
                'numberSequences' => DB::table('number_sequence')->count(),
                'unreadNotifications' => DB::table('notification')
                    ->where('user_id', auth()->id())
                    ->where('read', false)
                    ->count(),
            ],
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
            'companies' => Company::query()
                ->withCount('branches')
                ->orderBy('created_at')
                ->get()
                ->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'name' => $this->modelTranslation($company, 'name', $locale),
                    'baseCurrency' => $company->base_currency,
                    'branchCount' => $company->branches_count,
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
                ->with('company')
                ->orderBy('company_id')
                ->orderBy('code')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'companyName' => $branch->company
                        ? $this->modelTranslation($branch->company, 'name', $locale)
                        : '',
                    'code' => $branch->code,
                    'name' => $this->modelTranslation($branch, 'name', $locale),
                    'isActive' => $branch->is_active,
                ])
                ->values(),
        ]);
    }

    public function numbering(Request $request): Response
    {
        $locale = $this->locale($request);

        return Inertia::render('Settings/Numbering', [
            'sequences' => DB::table('number_sequence')
                ->leftJoin('company', 'company.id', '=', 'number_sequence.company_id')
                ->select([
                    'number_sequence.id',
                    'number_sequence.company_id',
                    'number_sequence.key',
                    'number_sequence.doc_type',
                    'number_sequence.prefix',
                    'number_sequence.include_year',
                    'number_sequence.include_branch',
                    'number_sequence.padding',
                    'number_sequence.reset_policy',
                    'number_sequence.next_value',
                    'company.name as company_name',
                ])
                ->orderBy('number_sequence.doc_type')
                ->get()
                ->map(fn (object $sequence): array => [
                    'id' => $sequence->id,
                    'companyName' => $this->translationFromJson($sequence->company_name, $locale),
                    'key' => $sequence->key,
                    'docType' => $sequence->doc_type,
                    'prefix' => $sequence->prefix,
                    'includeYear' => (bool) $sequence->include_year,
                    'includeBranch' => (bool) $sequence->include_branch,
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
                        ->pluck('name')
                        ->sort()
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
        ]);
    }

    public function notifications(Request $request): Response
    {
        $locale = $this->locale($request);

        return Inertia::render('Notifications', [
            'items' => DB::table('notification')
                ->leftJoin('company', 'company.id', '=', 'notification.company_id')
                ->where('notification.user_id', $request->user()->id)
                ->select([
                    'notification.id',
                    'notification.type',
                    'notification.target_ref',
                    'notification.read',
                    'notification.at',
                    'company.name as company_name',
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
                    'companyName' => $this->translationFromJson($notification->company_name, $locale),
                ])
                ->values(),
        ]);
    }

    public function markNotificationRead(Request $request, string $id): RedirectResponse
    {
        DB::table('notification')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['read' => true]);

        return back()->with('success', __('Notification marked as read.'));
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
