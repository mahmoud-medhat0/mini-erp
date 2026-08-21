<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SettingsActionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    public function storeCompany(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.company');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $id = (string) Str::uuid();

        $settingsJson = json_encode([
            'legal_name' => $validated['legal_name'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'fiscal_year_start_month' => $validated['fiscal_year_start_month'] ?? 1,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'address' => $validated['address'] ?? null,
        ], JSON_THROW_ON_ERROR);

        DB::table('company')->insert([
            'id' => $id,
            'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
            'base_currency' => $validated['base_currency'],
            'settings_json' => $settingsJson,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->auditLogger->record($request->user()->id, 'company.create', 'company', $id, after: $validated);

        return back()->with('success', __('Company saved.'));
    }

    public function updateCompany(Request $request, ?string $companyId = null): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.company');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['required', 'string', 'size:3', 'exists:currency,code'],
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $companyRecord = null;
        if ($companyId) {
            $companyRecord = DB::table('company')->where('id', $companyId)->first();
        } else {
            $companyRecord = DB::table('company')->orderBy('created_at')->first();
        }

        if (! $companyRecord) {
            $companyId = (string) Str::uuid();
            $settingsJson = json_encode([
                'legal_name' => $validated['legal_name'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'registration_number' => $validated['registration_number'] ?? null,
                'fiscal_year_start_month' => $validated['fiscal_year_start_month'] ?? 1,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'website' => $validated['website'] ?? null,
                'address' => $validated['address'] ?? null,
            ], JSON_THROW_ON_ERROR);

            DB::table('company')->insert([
                'id' => $companyId,
                'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
                'base_currency' => $validated['base_currency'],
                'settings_json' => $settingsJson,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->auditLogger->record($request->user()->id, 'company.create', 'company', $companyId, after: $validated);

            return back()->with('success', __('Company created.'));
        }

        $before = (array) $companyRecord;
        $companyId = (string) $companyRecord->id;
        $existingSettings = json_decode($companyRecord->settings_json ?? '{}', true) ?: [];

        $newSettings = array_merge($existingSettings, array_filter([
            'legal_name' => $validated['legal_name'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'fiscal_year_start_month' => $validated['fiscal_year_start_month'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'address' => $validated['address'] ?? null,
        ], fn ($val) => $val !== null));

        if (isset($validated['lock_version'])) {
            $this->optimisticLock->update('company', ['id' => $companyId], (int) $validated['lock_version'], [
                'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
                'base_currency' => $validated['base_currency'],
                'settings_json' => json_encode($newSettings, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('company')->where('id', $companyId)->update([
                'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
                'base_currency' => $validated['base_currency'],
                'settings_json' => json_encode($newSettings, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }

        $this->auditLogger->record($request->user()->id, 'company.update', 'company', $companyId, before: $before, after: $validated);

        return back()->with('success', __('Company saved.'));
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:branch,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable'],
        ]);

        $id = (string) Str::uuid();

        DB::table('branch')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
            'is_active' => $request->boolean('is_active', true),
            'lock_version' => 0,
        ]);

        $this->auditLogger->record($request->user()->id, 'branch.create', 'branch', $id, after: $validated);

        return back()->with('success', __('Branch saved.'));
    }

    public function updateBranch(Request $request, string $branchId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('branch', 'code')->ignore($branchId)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $before = (array) DB::table('branch')->where('id', $branchId)->first();

        abort_if($before === [], 404);

        if (isset($validated['lock_version'])) {
            $this->optimisticLock->update('branch', ['id' => $branchId], (int) $validated['lock_version'], [
                'code' => $validated['code'],
                'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
                'is_active' => $request->boolean('is_active'),
            ]);
        } else {
            DB::table('branch')->where('id', $branchId)->update([
                'code' => $validated['code'],
                'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
                'is_active' => $request->boolean('is_active'),
            ]);
        }

        $this->auditLogger->record($request->user()->id, 'branch.update', 'branch', $branchId, before: $before, after: $validated);

        return back()->with('success', __('Branch saved.'));
    }

    public function deleteBranch(Request $request, string $branchId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $branch = DB::table('branch')->where('id', $branchId)->first();
        abort_if(! $branch, 404);

        $before = (array) $branch;

        DB::table('branch')->where('id', $branchId)->delete();

        $this->auditLogger->record($request->user()->id, 'branch.delete', 'branch', $branchId, before: $before);

        return back()->with('success', __('Branch deleted successfully.'));
    }

    public function storeNumbering(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.numbering');

        $validated = $this->validateNumbering($request);
        $id = (string) Str::uuid();

        $exists = DB::table('number_sequence')
            ->where('key', $validated['key'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['key' => __('The numbering key already exists.')]);
        }

        DB::table('number_sequence')->insert([
            'id' => $id,
            ...$this->numberingPayload($validated),
        ]);

        $this->auditLogger->record($request->user()->id, 'number_sequence.create', 'number_sequence', $id, after: $validated);

        return back()->with('success', __('Numbering saved.'));
    }

    public function updateNumbering(Request $request, string $sequenceId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.numbering');

        $validated = $this->validateNumbering($request);

        return DB::transaction(function () use ($request, $sequenceId, $validated): RedirectResponse {
            $sequence = DB::table('number_sequence')
                ->where('id', $sequenceId)
                ->lockForUpdate()
                ->first();

            abort_if(! $sequence, 404);

            $before = (array) $sequence;

            if ($validated['key'] !== $sequence->key) {
                return back()->withErrors(['key' => __('Numbering keys cannot be changed after creation.')]);
            }

            if ((int) $validated['next_value'] < (int) $sequence->next_value) {
                return back()->withErrors(['next_value' => __('Cannot reduce next number below current sequence state.')]);
            }

            DB::table('number_sequence')
                ->where('id', $sequenceId)
                ->update($this->numberingPayload($validated));

            $this->auditLogger->record($request->user()->id, 'number_sequence.update', 'number_sequence', $sequenceId, before: $before, after: $validated);

            return back()->with('success', __('Numbering saved.'));
        });
    }

    public function assignRole(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $role = Role::query()->findOrFail($validated['role_id']);
        $user->assignRole($role);

        $this->auditLogger->record($request->user()->id, 'user.role.assigned', 'user', (string) $user->id, after: ['role' => $role->name]);

        return back()->with('success', __('Role assigned.'));
    }

    public function revokeRole(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $role = Role::query()->findOrFail($validated['role_id']);

        // Check if removing this role removes the last active super admin
        if ($this->isSuperAdminRole($role->name)) {
            $activeSuperAdmins = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%super%']))
                ->count();

            if ($activeSuperAdmins <= 1 && $user->is_active && $user->hasRole($role->name)) {
                return back()->withErrors(['role_id' => __('Cannot remove super admin role from the last active super admin user.')]);
            }
        }

        $user->removeRole($role);

        $this->auditLogger->record($request->user()->id, 'user.role.revoked', 'user', (string) $user->id, after: ['role' => $role->name]);

        return back()->with('success', __('Role revoked.'));
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'is_template' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $this->auditLogger->record($request->user()->id, 'role.create', 'role', (string) $role->id, after: $validated);

        return back()->with('success', __('Role created successfully.'));
    }

    public function updateRole(Request $request, int $roleId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $role = Role::query()->findOrFail($roleId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $role->toArray();
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        $this->auditLogger->record($request->user()->id, 'role.update', 'role', (string) $role->id, before: $before, after: $role->toArray());

        return back()->with('success', __('Role updated successfully.'));
    }

    public function deleteRole(Request $request, int $roleId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $role = Role::query()->findOrFail($roleId);

        abort_if($role->is_template, 403, 'Template roles cannot be deleted.');

        $before = $role->toArray();
        $role->delete();

        $this->auditLogger->record($request->user()->id, 'role.delete', 'role', (string) $roleId, before: $before);

        return back()->with('success', __('Role deleted successfully.'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'ar'])],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'locale' => $validated['locale'] ?? 'en',
            'theme' => 'dark',
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->filled('role_id')) {
            $role = Role::find($request->role_id);
            if ($role) {
                $user->assignRole($role);
            }
        }

        $this->auditLogger->record($request->user()->id, 'user.create', 'user', (string) $user->id, after: $user->toArray());

        return back()->with('success', __('User created successfully.'));
    }

    public function updateUser(Request $request, string $userId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'ar'])],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ]);

        // Check super admin protection when deactivating user or changing role
        $isSuperAdmin = $user->roles()->whereRaw('LOWER(name) LIKE ?', ['%super%'])->exists();
        if ($isSuperAdmin) {
            $activeSuperAdmins = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%super%']))
                ->count();

            if ($activeSuperAdmins <= 1) {
                if ($request->has('is_active') && ! $request->boolean('is_active')) {
                    return back()->withErrors(['is_active' => __('Cannot deactivate the last active super admin user.')]);
                }
                if ($request->has('role_id')) {
                    $newRoleId = $request->input('role_id');
                    $newRole = $newRoleId ? Role::find($newRoleId) : null;
                    if (! $newRole || ! $this->isSuperAdminRole($newRole->name)) {
                        return back()->withErrors(['role_id' => __('Cannot remove super admin role from the last active super admin user.')]);
                    }
                }
            }
        }

        $before = $user->toArray();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if ($request->filled('locale')) {
            $user->locale = $validated['locale'];
        }
        $user->is_active = $request->boolean('is_active', true);

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($request->has('role_id')) {
            if ($request->filled('role_id')) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $user->syncRoles([$role]);
                }
            } else {
                $user->syncRoles([]);
            }
        }

        $this->auditLogger->record($request->user()->id, 'user.update', 'user', (string) $user->id, before: $before, after: $user->toArray());

        return back()->with('success', __('User updated successfully.'));
    }

    public function deleteUser(Request $request, string $userId): RedirectResponse
    {
        $this->authorizeManagement($request, 'users.configure');

        if ((string) $userId === (string) $request->user()->id) {
            return back()->withErrors(['user' => __('You cannot delete your own user account.')]);
        }

        $user = User::findOrFail($userId);

        if ($user->roles()->whereRaw('LOWER(name) LIKE ?', ['%super%'])->exists()) {
            $activeSuperAdmins = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%super%']))
                ->count();

            if ($activeSuperAdmins <= 1) {
                return back()->withErrors(['user' => __('Cannot delete the last active super admin user.')]);
            }
        }

        $before = $user->toArray();
        $user->delete();

        $this->auditLogger->record($request->user()->id, 'user.delete', 'user', (string) $userId, before: $before);

        return back()->with('success', __('User deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNumbering(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'doc_type' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'max:20'],
            'include_year' => ['nullable'],
            'padding' => ['required', 'integer', 'min:1', 'max:12'],
            'reset_policy' => ['required', 'string', Rule::in(['never', 'yearly', 'monthly'])],
            'next_value' => ['required', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function numberingPayload(array $validated): array
    {
        return [
            'key' => $validated['key'],
            'doc_type' => $validated['doc_type'],
            'prefix' => $validated['prefix'],
            'include_year' => request()->boolean('include_year'),
            'padding' => (int) $validated['padding'],
            'reset_policy' => $validated['reset_policy'],
            'next_value' => (int) $validated['next_value'],
        ];
    }

    private function authorizeManagement(Request $request, string $permission): void
    {
        $user = $request->user();

        abort_if(! $user, 403);

        if ($user->can($permission) || $user->can('settings.configure')) {
            return;
        }

        abort(403);
    }

    private function isSuperAdminRole(string $roleName): bool
    {
        return str_contains(strtolower($roleName), 'super');
    }
}
