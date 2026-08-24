<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase8Slice3OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_route_reports_database_ready(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok');
    }

    public function test_scheduler_registers_bounded_token_cleanup(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())->first(
            fn ($event): bool => str_contains((string) $event->command, 'tokens:gc')
        );

        $this->assertNotNull($event, 'Scheduler must contain tokens:gc command.');
        $this->assertStringContainsString('--batch=100', (string) $event->command);
    }

    public function test_database_queue_baseline_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'), 'jobs table must exist.');
        $this->assertTrue(Schema::hasTable('failed_jobs'), 'failed_jobs table must exist.');
        $this->assertTrue(Schema::hasTable('job_batches'), 'job_batches table must exist.');
    }
}
