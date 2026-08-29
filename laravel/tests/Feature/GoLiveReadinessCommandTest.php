<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class GoLiveReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_live_readiness_command_passes_local_checks_without_exposing_sensitive_values(): void
    {
        config()->set('app.key', 'base64:LOCAL_TEST_KEY_VALUE');
        config()->set('database.connections.sqlite.password', 'local-db-secret-value');
        config()->set('mail.mailers.smtp.password', 'local-mail-secret-value');
        config()->set('filesystems.disks.s3.secret', 'local-s3-secret-value');

        $buffer = new BufferedOutput;
        $exit = Artisan::call('ops:go-live-readiness', [
            '--target' => 'local',
            '--strict' => true,
            '--json' => true,
        ], $buffer);

        $output = $buffer->fetch();
        $payload = $this->decodeReadinessJson($output);

        $this->assertSame(0, $exit);
        $this->assertSame('local', $payload['target']);
        $this->assertSame('ready', $payload['status']);
        $this->assertSame(0, $payload['blocking_failures']);
        $this->assertIsArray($payload['checks']);

        foreach ([
            'LOCAL_TEST_KEY_VALUE',
            'local-db-secret-value',
            'local-mail-secret-value',
            'local-s3-secret-value',
        ] as $secretValue) {
            $this->assertStringNotContainsString($secretValue, $output);
        }
    }

    public function test_go_live_readiness_command_strict_production_profile_blocks_unsafe_configuration(): void
    {
        config()->set('app.key', '');
        config()->set('app.debug', true);
        config()->set('app.url', 'http://localhost');
        config()->set('queue.default', 'sync');
        config()->set('mail.default', 'log');

        $buffer = new BufferedOutput;
        $exit = Artisan::call('ops:go-live-readiness', [
            '--target' => 'production',
            '--strict' => true,
            '--json' => true,
        ], $buffer);

        $payload = $this->decodeReadinessJson($buffer->fetch());
        $failedChecks = collect($payload['checks'])
            ->where('level', 'fail')
            ->pluck('name')
            ->all();

        $this->assertSame(1, $exit);
        $this->assertSame('production', $payload['target']);
        $this->assertSame('not_ready', $payload['status']);
        $this->assertContains('app.environment', $failedChecks);
        $this->assertContains('app.key', $failedChecks);
        $this->assertContains('app.debug', $failedChecks);
        $this->assertContains('app.url', $failedChecks);
        $this->assertContains('database.driver', $failedChecks);
        $this->assertContains('queue.connection', $failedChecks);
        $this->assertContains('mail.delivery', $failedChecks);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeReadinessJson(string $output): array
    {
        $start = strpos($output, '{');
        $this->assertNotFalse($start, 'Readiness command output must contain a JSON object.');

        $payload = json_decode(substr($output, $start), true);
        $this->assertIsArray($payload, 'Readiness command JSON output must decode to an array. Raw output: '.$output);

        return $payload;
    }
}
