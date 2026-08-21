<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_authenticated_users_to_the_migrated_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_foundation_diagnostic_page_renders_through_inertia(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/foundation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Foundation')
                ->where('status', 'M6 page migration')
                ->where('database', 'not_checked')
                ->where('auth.user.email', $user->email)
                ->has('notifications')
                ->etc());
    }
}
