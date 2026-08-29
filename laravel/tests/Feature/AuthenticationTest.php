<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BootstrapUserSeeder;
use Database\Seeders\FirstUserSuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_foundation_page(): void
    {
        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_login_page_renders_through_inertia(): void
    {
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->has('auth')
                ->has('flash')
                ->etc());
    }

    public function test_active_users_can_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
            'is_active' => true,
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'correct-password',
            ])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected_with_a_generic_error(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password',
            'is_active' => true,
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'correct-password',
            'is_active' => false,
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'inactive@example.com',
                'password' => 'correct-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_bootstrap_user_seeder_creates_local_login_credentials(): void
    {
        config()->set('erp_auth.bootstrap_user.enabled', true);
        config()->set('erp_auth.bootstrap_user.email', 'admin@mini-erp.local');
        config()->set('erp_auth.bootstrap_user.password', 'Password123!');

        $this->seed(BootstrapUserSeeder::class);

        $user = User::query()->where('email', 'admin@mini-erp.local')->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    public function test_bootstrap_user_role_assignment_can_be_disabled(): void
    {
        config()->set('erp_auth.bootstrap_user.enabled', true);
        config()->set('erp_auth.bootstrap_user.assign_role', false);
        config()->set('erp_auth.bootstrap_user.email', 'admin@mini-erp.local');
        config()->set('erp_auth.bootstrap_user.password', 'Password123!');

        Role::query()->create([
            'name' => 'ERP_ADMIN',
            'guard_name' => 'web',
            'is_template' => true,
        ]);

        $this->seed(BootstrapUserSeeder::class);

        $user = User::query()->where('email', 'admin@mini-erp.local')->firstOrFail();

        $this->assertFalse($user->hasRole('ERP_ADMIN'));
    }

    public function test_first_user_super_admin_seeder_does_not_assign_role_by_default(): void
    {
        $firstUser = User::factory()->create(['email' => 'first@example.com']);
        $secondUser = User::factory()->create(['email' => 'second@example.com']);

        Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'guard_name' => 'web',
            'is_template' => true,
        ]);

        $this->seed(FirstUserSuperAdminSeeder::class);

        $this->assertFalse($firstUser->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertFalse($secondUser->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertDatabaseMissing('activity_log', [
            'event' => 'first_user_super_admin.seed',
        ]);
    }

    public function test_first_user_super_admin_seeder_assigns_super_admin_to_the_first_user_only_when_explicitly_enabled(): void
    {
        config()->set('erp_auth.first_user_super_admin.enabled', true);

        $firstUser = User::factory()->create(['email' => 'first@example.com']);
        $secondUser = User::factory()->create(['email' => 'second@example.com']);

        Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'guard_name' => 'web',
            'is_template' => true,
        ]);

        $this->seed(FirstUserSuperAdminSeeder::class);

        $this->assertTrue($firstUser->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertFalse($secondUser->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertDatabaseHas('activity_log', [
            'event' => 'first_user_super_admin.seed',
            'properties->entity_id' => (string) $firstUser->id,
            'properties->after->assigned_role' => 'SUPER_ADMIN',
        ]);
    }

    public function test_first_user_super_admin_seeder_no_ops_when_no_users_exist(): void
    {
        config()->set('erp_auth.first_user_super_admin.enabled', true);

        Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'guard_name' => 'web',
            'is_template' => true,
        ]);

        $this->seed(FirstUserSuperAdminSeeder::class);

        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseMissing('activity_log', [
            'event' => 'first_user_super_admin.seed',
        ]);
    }

    public function test_first_user_super_admin_seeder_no_ops_when_role_does_not_exist(): void
    {
        config()->set('erp_auth.first_user_super_admin.enabled', true);

        $user = User::factory()->create(['email' => 'first@example.com']);

        $this->seed(FirstUserSuperAdminSeeder::class);

        $this->assertCount(0, $user->fresh()->roles);
        $this->assertDatabaseMissing('activity_log', [
            'event' => 'first_user_super_admin.seed',
        ]);
    }

    public function test_first_user_super_admin_seeder_throws_in_production_when_enabled_without_confirmation(): void
    {
        $this->app['env'] = 'production';
        config()->set('erp_auth.first_user_super_admin.enabled', true);
        config()->set('erp_auth.first_user_super_admin.production_confirmation', null);

        $user = User::factory()->create(['email' => 'first@example.com']);

        Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'guard_name' => 'web',
            'is_template' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Production first-user Super Admin assignment requires exact confirmation phrase match.');

        try {
            app(FirstUserSuperAdminSeeder::class)->run();
        } finally {
            $this->assertFalse($user->fresh()->hasRole('SUPER_ADMIN'));
            $this->assertDatabaseMissing('activity_log', [
                'event' => 'first_user_super_admin.seed',
            ]);
        }
    }

    public function test_first_user_super_admin_seeder_assigns_in_production_when_exact_confirmation_is_provided(): void
    {
        $this->app['env'] = 'production';
        config()->set('erp_auth.first_user_super_admin.enabled', true);
        config()->set('erp_auth.first_user_super_admin.production_confirmation', 'CONFIRM_ASSIGN_FIRST_USER_SUPER_ADMIN');
        config()->set('erp_auth.first_user_super_admin.required_production_confirmation', 'CONFIRM_ASSIGN_FIRST_USER_SUPER_ADMIN');

        $firstUser = User::factory()->create(['email' => 'first@example.com']);
        $secondUser = User::factory()->create(['email' => 'second@example.com']);

        Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'guard_name' => 'web',
            'is_template' => true,
        ]);

        app(FirstUserSuperAdminSeeder::class)->run();

        $this->assertTrue($firstUser->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertFalse($secondUser->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertDatabaseHas('activity_log', [
            'event' => 'first_user_super_admin.seed',
            'properties->entity_id' => (string) $firstUser->id,
            'properties->after->assigned_role' => 'SUPER_ADMIN',
        ]);
    }

    public function test_first_user_super_admin_seeder_does_not_duplicate_audit_if_already_assigned(): void
    {
        config()->set('erp_auth.first_user_super_admin.enabled', true);

        $role = Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'guard_name' => 'web',
            'is_template' => true,
        ]);

        $firstUser = User::factory()->create(['email' => 'first@example.com']);
        $firstUser->assignRole($role);

        $this->seed(FirstUserSuperAdminSeeder::class);

        $this->assertDatabaseMissing('activity_log', [
            'event' => 'first_user_super_admin.seed',
        ]);
    }

    public function test_login_regenerates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $this->get('/login');
        $initialSessionId = session()->getId();

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($initialSessionId, session()->getId());
    }

    public function test_logout_invalidates_session_and_clears_authenticated_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_bootstrap_user_seeder_default_password_passes_policy_and_authenticates(): void
    {
        config()->set('erp_auth.bootstrap_user.enabled', true);
        config()->set('erp_auth.bootstrap_user.email', 'admin@mini-erp.local');
        config()->set('erp_auth.bootstrap_user.password', 'Password123!');

        $this->seed(BootstrapUserSeeder::class);

        $user = User::query()->where('email', 'admin@mini-erp.local')->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Password123!', $user->password));

        $this->post('/login', [
            'email' => 'admin@mini-erp.local',
            'password' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }
}
