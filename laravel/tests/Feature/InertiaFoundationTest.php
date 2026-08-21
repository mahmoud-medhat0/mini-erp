<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_page_renders_through_inertia(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Foundation')
                ->where('status', 'M4 auth foundation')
                ->where('database', 'not_checked')
                ->where('auth.user.email', $user->email)
                ->has('tenant')
                ->has('notifications')
                ->etc());
    }
}
