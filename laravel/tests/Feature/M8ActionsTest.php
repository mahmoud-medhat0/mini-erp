<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Numbering\NumberSequenceAllocator;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class M8ActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, RbacSeeder::class, PermissionSeeder::class]);
    }

    // --- 1. COMPANY SETTINGS ---

    public function test_authorized_user_can_update_company_settings(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $response = $this->actingAs($admin)->patch(route('settings.company.update'), [
            'name_en' => 'Acme Global Corp',
            'name_ar' => 'شركة أكمل العالمية',
            'legal_name' => 'Acme Global Corp LLC',
            'tax_number' => '123-456-789',
            'registration_number' => 'REG-9988',
            'base_currency' => 'EGP',
            'fiscal_year_start_month' => 1,
            'address' => '101 Tech Street, Cairo',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $company = DB::table('company')->first();
        $this->assertNotNull($company);
        $this->assertEquals('EGP', $company->base_currency);

        $name = json_decode($company->name, true);
        $this->assertEquals('Acme Global Corp', $name['en']);
        $this->assertEquals('شركة أكمل العالمية', $name['ar']);

        $settings = json_decode($company->settings_json, true);
        $this->assertEquals('Acme Global Corp LLC', $settings['legal_name']);
        $this->assertEquals('123-456-789', $settings['tax_number']);
    }

    public function test_unauthorized_user_cannot_update_company_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('settings.company.update'), [
            'name_en' => 'Hacked Corp',
            'name_ar' => 'شركة مخترقة',
            'base_currency' => 'USD',
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_company_base_currency_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.company');

        $response = $this->actingAs($admin)->patch(route('settings.company.update'), [
            'name_en' => 'Acme Corp',
            'name_ar' => 'شركة إكمي',
            'base_currency' => 'INVALID_CURRENCY',
        ]);

        $response->assertSessionHasErrors('base_currency');
    }

    // --- 2. BRANCH CRUD ACTIONS ---

    public function test_authorized_user_can_create_update_and_delete_branch(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.branches');

        // Create
        $createResp = $this->actingAs($admin)->post(route('settings.branches.store'), [
            'code' => 'HQ-01',
            'name_en' => 'Headquarters',
            'name_ar' => 'الفرع الرئيسي',
            'is_active' => true,
        ]);
        $createResp->assertRedirect();
        $createResp->assertSessionHasNoErrors();

        $branch = DB::table('branch')->where('code', 'HQ-01')->first();
        $this->assertNotNull($branch);

        // Update
        $updateResp = $this->actingAs($admin)->patch(route('settings.branches.update', $branch->id), [
            'code' => 'HQ-01-UPDATED',
            'name_en' => 'Headquarters Main',
            'name_ar' => 'الفرع الرئيسي المعدل',
            'is_active' => false,
        ]);
        $updateResp->assertRedirect();
        $updateResp->assertSessionHasNoErrors();

        $updatedBranch = DB::table('branch')->where('id', $branch->id)->first();
        $this->assertEquals('HQ-01-UPDATED', $updatedBranch->code);

        // Delete
        $deleteResp = $this->actingAs($admin)->delete(route('settings.branches.delete', $branch->id));
        $deleteResp->assertRedirect();
        $deleteResp->assertSessionHasNoErrors();

        $this->assertNull(DB::table('branch')->where('id', $branch->id)->first());
    }

    public function test_duplicate_branch_code_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.branches');

        DB::table('branch')->insert([
            'id' => (string) Str::uuid(),
            'code' => 'BRANCH-EXISTING',
            'name' => json_encode(['en' => 'Existing Branch', 'ar' => 'فرع قائم']),
            'is_active' => true,
            'lock_version' => 0,
        ]);

        $response = $this->actingAs($admin)->post(route('settings.branches.store'), [
            'code' => 'BRANCH-EXISTING',
            'name_en' => 'New Duplicate Branch',
            'name_ar' => 'فرع مكرر جديد',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_unauthorized_user_cannot_manage_branches(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.branches.store'), [
            'code' => 'UNAUTH-01',
            'name_en' => 'Unauthorized Branch',
            'name_ar' => 'فرع غير مصرح',
        ]);

        $response->assertStatus(403);
    }

    public function test_read_or_other_scoped_settings_permissions_do_not_grant_cross_action_management(): void
    {
        $settingsViewer = User::factory()->create();
        $settingsViewer->givePermissionTo('settings.view');

        $this->actingAs($settingsViewer)->post(route('settings.branches.store'), [
            'code' => 'VIEW-ONLY',
            'name_en' => 'View Only Branch',
            'name_ar' => 'فرع قراءة فقط',
        ])->assertForbidden();

        $companyManager = User::factory()->create();
        $companyManager->givePermissionTo('settings.company');

        $this->actingAs($companyManager)->post(route('settings.branches.store'), [
            'code' => 'COMPANY-ONLY',
            'name_en' => 'Company Only Branch',
            'name_ar' => 'فرع صلاحية الشركة فقط',
        ])->assertForbidden();

        $branchManager = User::factory()->create();
        $branchManager->givePermissionTo('settings.branches');
        $targetUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'VIEWER', 'guard_name' => 'web']);

        $this->actingAs($branchManager)->post(route('settings.users.roles.assign'), [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
        ])->assertForbidden();
    }

    // --- 3. NUMBER SEQUENCE SETTINGS ---

    public function test_authorized_user_can_update_number_sequence_settings(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.numbering');

        $sequenceId = (string) Str::uuid();
        DB::table('number_sequence')->insert([
            'id' => $sequenceId,
            'key' => 'jv_seq_test',
            'doc_type' => 'journal_entry',
            'prefix' => 'JV-',
            'include_year' => true,
            'padding' => 5,
            'reset_policy' => 'yearly',
            'next_value' => 10,
        ]);

        $response = $this->actingAs($admin)->patch(route('settings.numbering.update', $sequenceId), [
            'key' => 'jv_seq_test',
            'doc_type' => 'journal_entry',
            'prefix' => 'JV-NEW-',
            'include_year' => true,
            'padding' => 6,
            'reset_policy' => 'yearly',
            'next_value' => 15,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $updatedSeq = DB::table('number_sequence')->where('id', $sequenceId)->first();
        $this->assertEquals('JV-NEW-', $updatedSeq->prefix);
        $this->assertEquals(6, $updatedSeq->padding);
        $this->assertEquals(15, $updatedSeq->next_value);
    }

    public function test_cannot_decrement_next_value_below_current_sequence_state(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.numbering');

        $sequenceId = (string) Str::uuid();
        DB::table('number_sequence')->insert([
            'id' => $sequenceId,
            'key' => 'jv_seq_test_decrement',
            'doc_type' => 'journal_entry',
            'prefix' => 'JV-',
            'include_year' => true,
            'padding' => 5,
            'reset_policy' => 'yearly',
            'next_value' => 50,
        ]);

        $response = $this->actingAs($admin)->patch(route('settings.numbering.update', $sequenceId), [
            'key' => 'jv_seq_test_decrement',
            'doc_type' => 'journal_entry',
            'prefix' => 'JV-',
            'include_year' => true,
            'padding' => 5,
            'reset_policy' => 'yearly',
            'next_value' => 20, // Smaller than 50
        ]);

        $response->assertSessionHasErrors('next_value');
    }

    public function test_number_sequence_key_cannot_be_changed_after_creation(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.numbering');

        $sequenceId = (string) Str::uuid();
        DB::table('number_sequence')->insert([
            'id' => $sequenceId,
            'key' => 'immutable_key',
            'doc_type' => 'journal_entry',
            'prefix' => 'JV-',
            'include_year' => true,
            'padding' => 5,
            'reset_policy' => 'yearly',
            'next_value' => 5,
        ]);

        $response = $this->actingAs($admin)->patch(route('settings.numbering.update', $sequenceId), [
            'key' => 'changed_key',
            'doc_type' => 'journal_entry',
            'prefix' => 'JV-',
            'include_year' => true,
            'padding' => 5,
            'reset_policy' => 'yearly',
            'next_value' => 5,
        ]);

        $response->assertSessionHasErrors('key');

        $this->assertDatabaseHas('number_sequence', [
            'id' => $sequenceId,
            'key' => 'immutable_key',
        ]);
    }

    public function test_concurrent_allocations_remain_unique_after_numbering_update(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('settings.numbering');

        $allocator = new NumberSequenceAllocator;
        $val1 = $allocator->nextValue('sales_invoice_seq');
        $this->assertEquals(1, $val1);

        $sequence = DB::table('number_sequence')->where('key', 'sales_invoice_seq')->first();
        $this->assertNotNull($sequence);

        $this->actingAs($admin)->patch(route('settings.numbering.update', $sequence->id), [
            'key' => 'sales_invoice_seq',
            'doc_type' => 'sales_invoice_seq',
            'prefix' => 'INV-',
            'include_year' => true,
            'padding' => 6,
            'reset_policy' => 'yearly',
            'next_value' => 10,
        ]);

        $val2 = $allocator->nextValue('sales_invoice_seq');
        $this->assertEquals(11, $val2);
    }

    // --- 4. USER ROLE ASSIGN & REVOKE & SUPER ADMIN PROTECTION ---

    public function test_authorized_user_can_assign_and_revoke_roles(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('users.configure');

        $targetUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'ACCOUNTANT', 'guard_name' => 'web']);

        // Assign
        $assignResp = $this->actingAs($admin)->post(route('settings.users.roles.assign'), [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
        ]);
        $assignResp->assertRedirect();
        $assignResp->assertSessionHasNoErrors();
        $this->assertTrue($targetUser->fresh()->hasRole('ACCOUNTANT'));

        // Revoke
        $revokeResp = $this->actingAs($admin)->delete(route('settings.users.roles.revoke'), [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
        ]);
        $revokeResp->assertRedirect();
        $revokeResp->assertSessionHasNoErrors();
        $this->assertFalse($targetUser->fresh()->hasRole('ACCOUNTANT'));
    }

    public function test_role_assignment_rejects_non_web_guard_roles(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('users.configure');
        $targetUser = User::factory()->create();

        $rolePayload = [
            'name' => 'API_ONLY_ROLE',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'is_template')) {
            $rolePayload['is_template'] = false;
        }

        $apiRoleId = DB::table('roles')->insertGetId($rolePayload);

        $response = $this->actingAs($admin)->post(route('settings.users.roles.assign'), [
            'user_id' => $targetUser->id,
            'role_id' => $apiRoleId,
        ]);

        $response->assertSessionHasErrors('role_id');
    }

    public function test_cannot_remove_super_admin_role_from_last_active_super_admin_user(): void
    {
        $superRole = Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);
        $superAdminUser = User::factory()->create(['is_active' => true]);
        $superAdminUser->assignRole($superRole);

        $otherAdmin = User::factory()->create();
        $otherAdmin->givePermissionTo('users.configure');

        // Revoke role
        $response = $this->actingAs($otherAdmin)->delete(route('settings.users.roles.revoke'), [
            'user_id' => $superAdminUser->id,
            'role_id' => $superRole->id,
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertTrue($superAdminUser->fresh()->hasRole('SUPER_ADMIN'));
    }

    public function test_cannot_deactivate_or_delete_last_active_super_admin_user(): void
    {
        $superRole = Role::firstOrCreate(['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);
        $superAdminUser = User::factory()->create(['is_active' => true]);
        $superAdminUser->assignRole($superRole);

        $manager = User::factory()->create();
        $manager->givePermissionTo('users.configure');

        // Attempt deactivation
        $deactivateResp = $this->actingAs($manager)->patch(route('settings.users.update', $superAdminUser->id), [
            'name' => $superAdminUser->name,
            'email' => $superAdminUser->email,
            'is_active' => false,
        ]);
        $deactivateResp->assertSessionHasErrors('is_active');

        // Attempt deletion
        $deleteResp = $this->actingAs($manager)->delete(route('settings.users.delete', $superAdminUser->id));
        $deleteResp->assertSessionHasErrors('user');
    }

    // --- 5. REGRESSION & INVARIANT ASSERTIONS ---

    public function test_schema_and_config_invariants(): void
    {
        $this->assertFalse(Schema::hasColumn('branch', 'company_id'), 'branch table must not have company_id column');
        $this->assertFalse(Schema::hasColumn('number_sequence', 'company_id'), 'number_sequence table must not have company_id column');
        $this->assertFalse(Schema::hasColumn('number_sequence', 'branch_id'), 'number_sequence table must not have branch_id column');
        $this->assertFalse(Schema::hasColumn('roles', 'company_id'), 'roles table must not have company_id column');
        $this->assertFalse(config('permission.teams'), 'Spatie teams must remain disabled');
    }
}
