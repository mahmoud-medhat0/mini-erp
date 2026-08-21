<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaFoundationTest extends TestCase
{
    public function test_foundation_page_renders_through_inertia(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Foundation')
                ->where('status', 'M2 foundation')
                ->where('database', 'not_checked')
                ->has('auth')
                ->has('tenant')
                ->has('notifications')
                ->etc());
    }
}
