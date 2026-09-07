<?php

namespace Tests\Feature;

use App\Application\Notifications\NotificationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_table_filters_searches_and_remains_user_scoped(): void
    {
        $service = app(NotificationService::class);
        $user = User::factory()->create();
        $other = User::factory()->create();

        $service->create($user->id, 'invoice_posted', 'INV-DT-001');
        $read = $service->create($user->id, 'payment_received', 'PAY-DT-001');
        $service->markRead($user->id, $read['id']);
        $service->create($other->id, 'invoice_posted', 'INV-DT-OTHER');

        $response = $this->actingAs($user)->getJson(
            '/notifications/data?'.http_build_query($this->gridQuery([
                'tab' => 'unread',
                'search' => ['value' => 'INV-DT', 'regex' => 'false'],
            ])),
        );

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_ref', 'INV-DT-001')
            ->assertJsonPath('data.0.read', false);
    }

    public function test_notification_table_requires_authentication(): void
    {
        $this->getJson('/notifications/data?'.http_build_query($this->gridQuery()))
            ->assertUnauthorized();
    }

    /** @param array<string, mixed> $overrides */
    private function gridQuery(array $overrides = []): array
    {
        $columns = [
            ['type', true, true],
            ['target_ref', true, true],
            ['at', false, true],
            ['read', false, true],
            ['actions', false, false],
        ];

        return array_replace_recursive([
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => array_map(fn (array $column): array => [
                'data' => $column[0],
                'name' => $column[0],
                'searchable' => $column[1] ? 'true' : 'false',
                'orderable' => $column[2] ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => '2', 'dir' => 'desc']],
            'search' => ['value' => '', 'regex' => 'false'],
        ], $overrides);
    }
}
