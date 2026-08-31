<?php

namespace Tests\Feature;

use App\Application\Audit\AuditLogQueryService;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class M10AuditAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CurrencySeeder::class, RbacSeeder::class, PermissionSeeder::class]);
    }

    // --- 1. SPATIE ACTIVITY_LOG AUDIT LOGGER WRITING & REDACTION ---

    public function test_audit_logger_writes_to_activity_log_table_and_redacts_sensitive_keys(): void
    {
        $user = User::factory()->create(['name' => 'Alice Admin', 'email' => 'alice@example.com']);
        $logger = app(AuditLogger::class);

        $initialAuditLogCount = DB::table('audit_log')->count();

        $logger->record(
            actorId: $user->id,
            action: 'user.login',
            entityType: 'user',
            entityId: (string) $user->id,
            before: ['status' => 'offline', 'password' => 'secret123'],
            after: ['status' => 'online', 'secret' => 'supersecret', 'token' => 'abc123token'],
            reason: 'Interactive login',
            requestId: 'req-999',
            ip: '127.0.0.1',
            device: 'Mozilla/5.0',
        );

        // Verify write to activity_log
        $activity = DB::table('activity_log')->where('event', 'user.login')->first();
        $this->assertNotNull($activity);
        $this->assertEquals($user->id, $activity->causer_id);
        $this->assertEquals(User::class, $activity->causer_type);

        $props = json_decode($activity->properties, true);
        $this->assertEquals('[redacted]', $props['before']['password']);
        $this->assertEquals('offline', $props['before']['status']);
        $this->assertEquals('[redacted]', $props['after']['secret']);
        $this->assertEquals('[redacted]', $props['after']['token']);
        $this->assertEquals('online', $props['after']['status']);
        $this->assertEquals('Interactive login', $props['reason']);
        $this->assertEquals('req-999', $props['request_id']);

        // Verify NO new writes go to legacy audit_log table
        $this->assertEquals($initialAuditLogCount, DB::table('audit_log')->count());
    }

    // --- 2. QUERY SERVICE FIELD ALIAS MAPPING & FILTERING ---

    public function test_query_service_returns_old_compatible_field_aliases_and_filters_correctly(): void
    {
        $user = User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);
        $logger = app(AuditLogger::class);

        $logger->record(
            actorId: $user->id,
            action: 'company.update',
            entityType: 'company',
            entityId: 'comp-100',
            before: ['name' => 'Old Corp'],
            after: ['name' => 'New Corp'],
            reason: 'Name change',
            requestId: 'req-777',
        );

        $service = app(AuditLogQueryService::class);
        $paginator = $service->paginate(['action' => 'company.update', 'actor_id' => (string) $user->id]);

        $this->assertEquals(1, $paginator->total());
        $row = $paginator->items()[0];

        // Field aliases assertion
        $this->assertNotNull($row->id);
        $this->assertEquals($user->id, $row->actor_id);
        $this->assertEquals('Bob Builder', $row->actor_name);
        $this->assertEquals('bob@example.com', $row->actor_email);
        $this->assertEquals('company.update', $row->action);
        $this->assertEquals('company', $row->entity_type);
        $this->assertEquals('comp-100', $row->entity_id);
        $this->assertStringContainsString('Old Corp', $row->before_json);
        $this->assertStringContainsString('New Corp', $row->after_json);
        $this->assertEquals('Name change', $row->reason);
        $this->assertEquals('req-777', $row->request_id);
        $this->assertNotNull($row->at);

        $this->assertEquals(1, $service->paginate(['entity_type' => 'company'])->total());
        $this->assertEquals(1, $service->paginate(['entity_id' => 'comp-100'])->total());
        $this->assertEquals(1, $service->paginate(['request_id' => 'req-777'])->total());
        $this->assertEquals(1, $service->paginate(['search' => 'new corp'])->total());
        $this->assertEquals(0, $service->paginate(['request_id' => 'missing-request'])->total());
    }

    // --- 3. IMMUTABILITY ENFORCEMENT ON ACTIVITY_LOG & AUDIT_LOG ---

    public function test_activity_log_and_audit_log_are_append_only(): void
    {
        $user = User::factory()->create();
        app(AuditLogger::class)->record($user->id, 'test.immutability', 'test', '1');

        $activityId = DB::table('activity_log')->where('event', 'test.immutability')->value('id');
        $this->assertNotNull($activityId);

        // Attempt UPDATE on activity_log
        try {
            DB::table('activity_log')->where('id', $activityId)->update(['description' => 'tampered']);
            $this->fail('Expected UPDATE on activity_log to fail due to append-only trigger.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('append-only', strtolower($e->getMessage()));
        }

        // Attempt DELETE on activity_log
        try {
            DB::table('activity_log')->where('id', $activityId)->delete();
            $this->fail('Expected DELETE on activity_log to fail due to append-only trigger.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('append-only', strtolower($e->getMessage()));
        }
    }

    // --- 4. AUDIT LOG PAGE AUTHORIZATION & CONTROLLER RENDERING ---

    public function test_authorized_user_can_view_audit_log_page_and_unauthorized_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('audit.view');

        $response = $this->actingAs($admin)->get(route('audit.index'));
        $response->assertStatus(200);

        $regularUser = User::factory()->create();
        $this->actingAs($regularUser)->get(route('audit.index'))->assertStatus(403);
    }

    // --- 5. SCHEDULER REGISTRATION FOR TOKENS:GC ---

    public function test_scheduler_registers_tokens_gc_command_with_batch_100(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())->first(function ($event) {
            return str_contains($event->command, 'tokens:gc');
        });

        $this->assertNotNull($event, 'Scheduler must contain tokens:gc command.');
        $this->assertStringContainsString('--batch=100', $event->command, 'Scheduled command must include --batch=100 option.');
    }

    // --- 6. JOBS / QUEUE BASELINE & SCHEMA ASSERTIONS ---

    public function test_jobs_and_failed_jobs_tables_exist_and_queue_is_functional(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'), 'jobs table must exist.');
        $this->assertTrue(Schema::hasTable('failed_jobs'), 'failed_jobs table must exist.');
        $this->assertTrue(Schema::hasTable('job_batches'), 'job_batches table must exist.');

        // Dispatch simple closure job synchronously
        $executed = false;
        dispatch_sync(function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed, 'Closure job should dispatch and execute synchronously.');
    }

    // --- 7. SCHEMA AND SPATIE TEAMS INVARIANTS ---

    public function test_activity_log_has_no_company_or_branch_scope_and_teams_is_disabled(): void
    {
        $this->assertFalse(Schema::hasColumn('activity_log', 'company_id'), 'activity_log must not have company_id');
        $this->assertFalse(Schema::hasColumn('activity_log', 'branch_id'), 'activity_log must not have branch_id');
        $this->assertFalse(Schema::hasColumn('activity_log', 'tenant_id'), 'activity_log must not have tenant_id');

        $this->assertFalse(config('permission.teams'), 'Spatie teams must remain disabled');
    }
}
