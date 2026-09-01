<?php

namespace Tests\Feature;

use App\Application\Settings\SuperAdminProtection;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_branch_and_numbering_settings_can_be_created_and_updated_without_invented_scope(): void
    {
        $user = $this->managementUser();

        $this->actingAs($user)
            ->post('/settings/company', [
                'name_en' => 'MDS',
                'name_ar' => 'إم دي إس',
                'base_currency' => 'EGP',
                'fiscal_year_start_month' => 7,
            ])
            ->assertRedirect();

        $company = Company::query()->firstOrFail();

        $this->assertSame('MDS', $company->getTranslation('name', 'en'));
        $this->assertDatabaseHas('activity_log', ['event' => 'company.create']);

        $this->actingAs($user)
            ->patch("/settings/company/{$company->id}", [
                'name_en' => 'MDS Updated',
                'name_ar' => 'إم دي إس محدثة',
                'base_currency' => 'USD',
                'lock_version' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('company', [
            'id' => $company->id,
            'base_currency' => 'USD',
            'lock_version' => 1,
        ]);
        $this->assertSame(7, (int) $company->fresh()->settings_json['fiscal_year_start_month']);

        $this->actingAs($user)
            ->post('/settings/company', [
                'name_en' => 'Second Company',
                'name_ar' => 'شركة ثانية',
                'base_currency' => 'EGP',
            ])
            ->assertSessionHasErrors('company');

        $this->assertDatabaseCount('company', 1);

        $this->actingAs($user)
            ->post('/settings/branches', [
                'code' => 'MAIN',
                'name_en' => 'Main Branch',
                'name_ar' => 'الفرع الرئيسي',
                'is_active' => true,
            ])
            ->assertRedirect();

        $branch = Branch::query()->firstOrFail();

        $this->actingAs($user)
            ->patch("/settings/branches/{$branch->id}", [
                'code' => 'HQ',
                'name_en' => 'Headquarters',
                'name_ar' => 'المقر الرئيسي',
                'is_active' => true,
                'lock_version' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branch', [
            'id' => $branch->id,
            'code' => 'HQ',
            'lock_version' => 1,
        ]);

        $this->actingAs($user)
            ->post('/settings/numbering', [
                'key' => 'sales.invoice',
                'doc_type' => 'SalesInvoice',
                'prefix' => 'INV',
                'include_year' => true,
                'padding' => 5,
                'reset_policy' => 'yearly',
                'next_value' => 1,
            ])
            ->assertRedirect();

        $sequenceId = DB::table('number_sequence')->value('id');

        $this->actingAs($user)
            ->patch("/settings/numbering/{$sequenceId}", [
                'key' => 'sales.invoice',
                'doc_type' => 'SalesInvoice',
                'prefix' => 'SI',
                'include_year' => true,
                'padding' => 6,
                'reset_policy' => 'monthly',
                'next_value' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('number_sequence', [
            'id' => $sequenceId,
            'prefix' => 'SI',
            'padding' => 6,
            'reset_policy' => 'monthly',
            'next_value' => 10,
        ]);
    }

    public function test_roles_can_be_assigned_and_revoked_with_users_configure_permission(): void
    {
        $manager = $this->managementUser();
        $target = User::factory()->create();
        $role = Role::query()->create(['name' => 'VIEWER', 'guard_name' => 'web', 'is_template' => true]);

        $this->actingAs($manager)
            ->post('/settings/users/roles', [
                'user_id' => $target->id,
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('VIEWER'));

        $this->actingAs($manager)
            ->delete('/settings/users/roles', [
                'user_id' => $target->id,
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $this->assertFalse($target->fresh()->hasRole('VIEWER'));
    }

    public function test_updating_user_profile_preserves_all_existing_roles(): void
    {
        $manager = $this->managementUser();
        $target = User::factory()->create();
        $viewer = Role::query()->create(['name' => 'VIEWER', 'guard_name' => 'web', 'is_template' => true]);
        $auditor = Role::query()->create(['name' => 'AUDITOR', 'guard_name' => 'web', 'is_template' => true]);
        $target->assignRole([$viewer, $auditor]);

        $this->actingAs($manager)
            ->patch("/settings/users/{$target->id}", [
                'name' => 'Updated Multi Role User',
                'email' => $target->email,
                'password' => '',
                'locale' => 'ar',
                'is_active' => true,
                'role_id' => $viewer->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['AUDITOR', 'VIEWER'],
            $target->fresh()->roles->pluck('name')->all()
        );
    }

    public function test_empty_rbac_assignments_do_not_grant_settings_management_access(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseCount('model_has_permissions', 0);

        $this->actingAs($user)
            ->post('/settings/company', [
                'name_en' => 'MDS',
                'name_ar' => 'إم دي إس',
                'base_currency' => 'EGP',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('company', 0);
    }

    public function test_template_roles_cannot_be_renamed_or_emptied(): void
    {
        $manager = $this->managementUser();
        $permission = Permission::query()->where('name', 'users.configure')->firstOrFail();
        $templateRole = Role::query()->create([
            'name' => 'ACCOUNTANT',
            'guard_name' => 'web',
            'is_template' => true,
        ]);
        $templateRole->givePermissionTo($permission);

        $this->actingAs($manager)
            ->patch("/settings/roles/{$templateRole->id}", [
                'name' => 'RENAMED_ACCOUNTANT',
                'permissions' => ['users.configure'],
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('ACCOUNTANT', $templateRole->fresh()->name);

        $this->actingAs($manager)
            ->patch("/settings/roles/{$templateRole->id}", [
                'name' => 'ACCOUNTANT',
                'permissions' => [],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertTrue($templateRole->fresh()->hasPermissionTo('users.configure'));
    }

    public function test_reserved_system_role_names_cannot_be_created_manually(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/roles', [
                'name' => 'viewer',
                'permissions' => ['users.configure'],
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('roles', ['name' => 'viewer']);
    }

    public function test_super_admin_role_keeps_its_identity_and_administration_permissions(): void
    {
        $manager = $this->managementUser();
        $superAdminRole = Role::query()->where('name', SuperAdminProtection::ROLE_NAME)->firstOrFail();
        $superAdminRole->update(['is_template' => false]);

        $this->actingAs($manager)
            ->patch("/settings/roles/{$superAdminRole->id}", [
                'name' => 'PLATFORM_ADMIN',
                'permissions' => ['settings.configure', 'users.configure'],
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($manager)
            ->patch("/settings/roles/{$superAdminRole->id}", [
                'name' => SuperAdminProtection::ROLE_NAME,
                'permissions' => ['settings.configure'],
            ])
            ->assertSessionHasErrors('permissions');

        $superAdminRole->refresh();
        $this->assertSame(SuperAdminProtection::ROLE_NAME, $superAdminRole->name);
        $this->assertTrue($superAdminRole->hasAllPermissions(['settings.configure', 'users.configure']));

        $this->actingAs($manager)
            ->delete("/settings/roles/{$superAdminRole->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $superAdminRole->id]);
    }

    public function test_unrelated_role_names_containing_super_are_not_treated_as_super_admin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $supervisor = Role::query()->create([
            'name' => 'SUPERVISOR',
            'guard_name' => 'web',
            'is_template' => false,
        ]);
        $user->assignRole($supervisor);

        $protection = app(SuperAdminProtection::class);

        $this->assertFalse($protection->isSuperAdmin($user));
        $this->assertFalse($protection->isSuperAdminRole($supervisor->name));
        $this->assertSame(0, $protection->activeSuperAdminCount());
    }

    private function managementUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'SUPER_ADMIN', 'guard_name' => 'web', 'is_template' => true]);

        foreach (['settings.configure', 'users.configure'] as $name) {
            $permission = Permission::query()->create([
                'name' => $name,
                'guard_name' => 'web',
                'module' => Str::before($name, '.'),
                'action' => Str::after($name, '.'),
            ]);
            $role->givePermissionTo($permission);
        }

        $user->assignRole($role);

        return $user;
    }
}
