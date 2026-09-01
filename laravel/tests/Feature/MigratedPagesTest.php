<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MigratedPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrated_app_pages_render_through_inertia(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'VIEWER', 'guard_name' => 'web', 'is_template' => true]);
        foreach (['dashboard.view', 'settings.view', 'settings.company', 'settings.branches', 'settings.numbering', 'users.configure'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $role->givePermissionTo(['dashboard.view', 'settings.view', 'settings.company', 'settings.branches', 'settings.numbering', 'users.configure']);
        $user->assignRole($role);

        $company = Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
        ]);

        Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MAIN',
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        ]);

        DB::table('number_sequence')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'sales.invoice',
            'doc_type' => 'SalesInvoice',
            'prefix' => 'INV',
            'include_year' => true,
            'padding' => 5,
            'reset_policy' => 'yearly',
            'next_value' => 1,
        ]);

        DB::table('notification')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'test_event',
            'target_ref' => 'demo:1',
            'read' => false,
            'at' => now(),
        ]);

        $this->actingAs($user);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->missing('counts.accounts')
                ->missing('counts.postedJournals')
                ->missing('counts.ledgerEntries')
                ->where('notifications.unreadCount', 1)
                ->where('health.activeBranches', 1)
                ->where('health.companyName', 'Demo Company')
                ->missing('counts.companies')
                ->missing('counts.branches')
                ->etc());

        $this->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('overview.companyRecords', 1)
                ->where('overview.activeBranches', 1)
                ->where('overview.numberSequences', 1)
                ->where('overview.activeUsers', 1)
                ->where('overview.completedEssentials', 4)
                ->where('overview.totalEssentials', 4)
                ->etc());

        $this->get('/settings/company')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Company')
                ->where('companies.0.name', 'Demo Company')
                ->etc());

        $this->get('/settings/branches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Branches')
                ->where('branches.0.code', 'MAIN')
                ->etc());

        $this->get('/settings/numbering')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Numbering')
                ->where('sequences.0.preview', 'INV-'.now()->year.'-00001')
                ->etc());

        $this->get('/settings/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Users')
                ->where('users.0.email', $user->email)
                ->where('roles.0.name', 'VIEWER')
                ->etc());

        $this->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications')
                ->where('items.data.0.targetRef', 'demo:1')
                ->where('notifications.unreadCount', 1)
                ->etc());
    }

    public function test_settings_hub_supports_scoped_administrators_without_leaking_other_section_counts(): void
    {
        $this->withoutVite();

        Permission::findOrCreate('settings.company', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('settings.company');
        Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => ['en' => 'Scoped Company', 'ar' => 'شركة محدودة'],
        ]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('overview.companyRecords', 1)
                ->where('overview.activeBranches', null)
                ->where('overview.numberSequences', null)
                ->where('overview.activeUsers', null)
                ->where('overview.activeApprovalRules', null)
                ->where('overview.completedEssentials', 1)
                ->where('overview.totalEssentials', 1)
                ->etc());
    }

    public function test_user_can_mark_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $company = Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
        ]);
        $notificationId = (string) Str::uuid();

        DB::table('notification')->insert([
            'id' => $notificationId,
            'user_id' => $user->id,
            'type' => 'test_event',
            'target_ref' => 'demo:1',
            'read' => false,
            'at' => now(),
        ]);

        $this->actingAs($user)
            ->from('/notifications')
            ->post("/notifications/{$notificationId}/read")
            ->assertRedirect('/notifications');

        $this->assertDatabaseHas('notification', [
            'id' => $notificationId,
            'read' => true,
        ]);
    }
}
