<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class GoLiveReadinessCommand extends Command
{
    protected $signature = 'ops:go-live-readiness
        {--target=local : Target profile: local, staging, or production}
        {--strict : Return non-zero when a blocking check fails}
        {--json : Emit machine-readable JSON without secret values}
        {--include-route-audit : Run the strict route authorization audit as part of this command}';

    protected $description = 'Run non-secret operational readiness checks before staging or production cutover.';

    public function handle(): int
    {
        $target = strtolower((string) $this->option('target'));
        if (! in_array($target, ['local', 'staging', 'production'], true)) {
            $this->error('Invalid target. Expected one of: local, staging, production.');

            return SymfonyCommand::FAILURE;
        }

        $checks = [
            $this->checkAppEnvironment($target),
            $this->checkAppKey(),
            $this->checkAppDebug($target),
            $this->checkAppUrl($target),
            $this->checkDatabaseConnection($target),
            $this->checkMigrationStatus(),
            $this->checkQueueConfiguration($target),
            $this->checkSchedulerRegistration(),
            $this->checkStoragePrivacy($target),
            $this->checkMailConfiguration($target),
            $this->checkLoggingConfiguration($target),
            $this->checkPermissionTeamsDisabled(),
            $this->checkOperationalTables(),
        ];

        if ($this->option('include-route-audit')) {
            $checks[] = $this->checkRouteAudit();
        }

        $summary = [
            'target' => $target,
            'status' => $this->hasBlockingFailure($checks) ? 'not_ready' : 'ready',
            'blocking_failures' => count(array_filter($checks, static fn (array $check): bool => $check['level'] === 'fail')),
            'warnings' => count(array_filter($checks, static fn (array $check): bool => $check['level'] === 'warn')),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->output->write(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        } else {
            $this->renderTable($summary);
        }

        if ($this->option('strict') && $summary['blocking_failures'] > 0) {
            return SymfonyCommand::FAILURE;
        }

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkAppEnvironment(string $target): array
    {
        $env = app()->environment();

        if ($target === 'staging' && $env !== 'staging') {
            return $this->checkFail('app.environment', 'Staging target requires APP_ENV=staging on the target host.');
        }

        if ($target === 'production' && $env !== 'production') {
            return $this->checkFail('app.environment', 'Production target requires APP_ENV=production on the target host.');
        }

        return $this->checkPass('app.environment', "Environment profile is {$env}.");
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkAppKey(): array
    {
        return filled(Config::get('app.key'))
            ? $this->checkPass('app.key', 'Application encryption key is configured.')
            : $this->checkFail('app.key', 'APP_KEY is missing. Generate a unique key before deployment.');
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkAppDebug(string $target): array
    {
        if ($target !== 'local' && (bool) Config::get('app.debug')) {
            return $this->checkFail('app.debug', 'APP_DEBUG must be false for staging and production.');
        }

        return $this->checkPass('app.debug', 'Debug mode policy is satisfied for the selected target.');
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkAppUrl(string $target): array
    {
        $url = (string) Config::get('app.url');

        if ($target !== 'local' && ! str_starts_with($url, 'https://')) {
            return $this->checkFail('app.url', 'APP_URL must use HTTPS for staging and production.');
        }

        return filled($url)
            ? $this->checkPass('app.url', 'Canonical application URL is configured.')
            : $this->checkFail('app.url', 'APP_URL is missing.');
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkDatabaseConnection(string $target): array
    {
        try {
            DB::select('select 1 as ok');
        } catch (Throwable $exception) {
            report($exception);

            return $this->checkFail('database.connection', 'Database connection failed.');
        }

        $driver = DB::connection()->getDriverName();
        if ($target !== 'local' && $driver !== 'pgsql') {
            return $this->checkFail('database.driver', 'Staging and production must use PostgreSQL.');
        }

        return $this->checkPass('database.connection', "Database connection is available using {$driver}.");
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkMigrationStatus(): array
    {
        $exit = Artisan::call('migrate:status', ['--no-interaction' => true]);
        $output = Artisan::output();

        if ($exit !== SymfonyCommand::SUCCESS) {
            return $this->checkFail('database.migrations', 'Migration status command failed.');
        }

        if (preg_match('/\bPending\b/i', $output) === 1) {
            return $this->checkFail('database.migrations', 'Pending migrations exist.');
        }

        return $this->checkPass('database.migrations', 'Migration status is clean with zero pending migrations.');
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkQueueConfiguration(string $target): array
    {
        $connection = (string) Config::get('queue.default');

        if ($target === 'production' && in_array($connection, ['sync', 'null'], true)) {
            return $this->checkFail('queue.connection', 'Production queue must use a supervised durable backend, not sync/null.');
        }

        if ($connection === 'database') {
            foreach (['jobs', 'failed_jobs', 'job_batches'] as $table) {
                if (! Schema::hasTable($table)) {
                    return $this->checkFail('queue.tables', "Database queue table {$table} is missing.");
                }
            }
        }

        return $this->checkPass('queue.connection', "Queue backend {$connection} is configured.");
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkSchedulerRegistration(): array
    {
        $exit = Artisan::call('schedule:list', ['--no-interaction' => true]);
        $output = Artisan::output();

        if ($exit !== SymfonyCommand::SUCCESS) {
            return $this->checkFail('scheduler.registration', 'Schedule list command failed.');
        }

        if (! str_contains($output, 'tokens:gc --batch=100')) {
            return $this->checkFail('scheduler.tokens_gc', 'tokens:gc --batch=100 is not registered in the scheduler.');
        }

        return $this->checkPass('scheduler.tokens_gc', 'tokens:gc --batch=100 is registered in the scheduler.');
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkStoragePrivacy(string $target): array
    {
        $disk = (string) Config::get('filesystems.default');

        if ($disk === 'local' && (bool) Config::get('filesystems.disks.local.serve')) {
            return $this->checkFail('storage.local_private', 'Local private storage serving must remain disabled.');
        }

        if ($target === 'production' && $disk === 's3') {
            foreach (['key', 'secret', 'region', 'bucket'] as $field) {
                if (! filled(Config::get("filesystems.disks.s3.{$field}"))) {
                    return $this->checkFail('storage.s3_configuration', 'S3 storage is selected but required private bucket configuration is incomplete.');
                }
            }
        }

        return $this->checkPass('storage.privacy', "Filesystem disk {$disk} satisfies private-delivery policy.");
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkMailConfiguration(string $target): array
    {
        $mailer = (string) Config::get('mail.default');

        if ($target === 'production' && in_array($mailer, ['log', 'array'], true)) {
            return $this->checkFail('mail.delivery', 'Production mail must use an approved external provider, not log/array.');
        }

        return $this->checkPass('mail.delivery', "Mail transport {$mailer} is configured for the selected target.");
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkLoggingConfiguration(string $target): array
    {
        $channel = (string) Config::get('logging.default');

        if ($target === 'production' && $channel === 'single') {
            return $this->checkWarn('logging.channel', 'Production should normally use daily/syslog/stack rotation instead of single file logging.');
        }

        return $this->checkPass('logging.channel', "Log channel {$channel} is configured.");
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkPermissionTeamsDisabled(): array
    {
        return Config::get('permission.teams') === false
            ? $this->checkPass('rbac.spatie_teams', 'Spatie Teams are disabled.')
            : $this->checkFail('rbac.spatie_teams', 'Spatie Teams must remain disabled.');
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkOperationalTables(): array
    {
        foreach (['users', 'sessions', 'activity_log'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->checkFail('schema.operational_tables', "Required operational table {$table} is missing.");
            }
        }

        return $this->checkPass('schema.operational_tables', 'Core operational tables are present.');
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkRouteAudit(): array
    {
        $exit = Artisan::call('security:route-audit', ['--strict' => true, '--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        if ($exit !== SymfonyCommand::SUCCESS || ! is_array($payload)) {
            return $this->checkFail('security.route_audit', 'Strict route authorization audit failed.');
        }

        $failing = (int) ($payload['counts']['failing'] ?? 0);
        if ($failing > 0) {
            return $this->checkFail('security.route_audit', "Strict route authorization audit reported {$failing} failing routes.");
        }

        return $this->checkPass('security.route_audit', "Strict route authorization audit passed for {$payload['total']} routes.");
    }

    /**
     * @param  list<array{name: string, level: string, message: string}>  $checks
     */
    private function hasBlockingFailure(array $checks): bool
    {
        return count(array_filter($checks, static fn (array $check): bool => $check['level'] === 'fail')) > 0;
    }

    /**
     * @param  array{target: string, status: string, blocking_failures: int, warnings: int, checks: list<array{name: string, level: string, message: string}>}  $summary
     */
    private function renderTable(array $summary): void
    {
        $this->info('Mini ERP - Go-Live Readiness Check');
        $this->line("Target: {$summary['target']}");
        $this->line("Status: {$summary['status']}");
        $this->line("Blocking failures: {$summary['blocking_failures']}");
        $this->line("Warnings: {$summary['warnings']}");
        $this->newLine();

        $this->table(
            ['Level', 'Check', 'Message'],
            array_map(
                static fn (array $check): array => [
                    strtoupper($check['level']),
                    $check['name'],
                    $check['message'],
                ],
                $summary['checks'],
            ),
        );
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkPass(string $name, string $message): array
    {
        return ['name' => $name, 'level' => 'pass', 'message' => $message];
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkWarn(string $name, string $message): array
    {
        return ['name' => $name, 'level' => 'warn', 'message' => $message];
    }

    /**
     * @return array{name: string, level: string, message: string}
     */
    private function checkFail(string $name, string $message): array
    {
        return ['name' => $name, 'level' => 'fail', 'message' => $message];
    }
}
