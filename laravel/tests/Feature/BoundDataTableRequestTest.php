<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundDataTableRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_named_datatable_feed_rejects_an_unbounded_page_size(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('customers.view');

        $query = [
            'draw' => '1',
            'start' => '0',
            'length' => '100000',
            'columns' => [[
                'data' => 'code',
                'name' => 'code',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ]],
            'order' => [],
            'search' => ['value' => '', 'regex' => 'false'],
        ];

        $this->actingAs($user)
            ->getJson('/customers/data?'.http_build_query($query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('length');
    }

    public function test_non_datatable_page_requests_are_unchanged(): void
    {
        $this->get('/login?draw=1')->assertOk();
    }
}
