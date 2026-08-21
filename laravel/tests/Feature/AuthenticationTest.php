<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BootstrapUserSeeder;
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
}
