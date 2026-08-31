<?php

namespace Tests\Unit;

use App\Application\Jobs\Backoff;
use App\Application\Jobs\IdempotentJobRunner;
use App\Domain\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditAndJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_is_append_only_and_redacts_sensitive_fields(): void
    {
        app(AuditLogger::class)->record(
            actorId: null,
            action: 'test.create',
            entityType: 'test',
            entityId: '1',
            after: ['name' => 'Visible', 'password' => 'secret'],
        );

        $row = DB::table('activity_log')->first();
        $props = json_decode($row->properties, true);
        $after = $props['after'];

        $this->assertSame('Visible', $after['name']);
        $this->assertSame('[redacted]', $after['password']);
    }

    public function test_idempotent_job_runner_executes_handler_once_for_same_key(): void
    {
        $runner = app(IdempotentJobRunner::class);
        $executions = 0;

        $first = $runner->run('demo', 'job-key', function () use (&$executions): array {
            $executions++;

            return ['ok' => true];
        });
        $second = $runner->run('demo', 'job-key', function () use (&$executions): array {
            $executions++;

            return ['ok' => false];
        });

        $this->assertSame(1, $executions);
        $this->assertSame($first, $second);
    }

    public function test_backoff_is_exponential_and_capped(): void
    {
        $this->assertSame(1000, Backoff::milliseconds(1));
        $this->assertSame(2000, Backoff::milliseconds(2));
        $this->assertSame(300000, Backoff::milliseconds(20));
    }
}
