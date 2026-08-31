<?php

namespace Tests\Feature;

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
