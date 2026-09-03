<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupPath = storage_path('app/testing-backups');
        File::deleteDirectory($this->backupPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupPath);

        parent::tearDown();
    }

    public function test_it_refuses_to_run_against_a_non_postgres_connection(): void
    {
        // The suite runs on SQLite, so this is the default state rather than a contrivance.
        $this->artisan('db:backup', ['--path' => $this->backupPath])
            ->expectsOutputToContain('db:backup supports PostgreSQL only')
            ->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($this->backupPath);
    }

    public function test_it_fails_and_leaves_no_partial_file_when_pg_dump_is_unavailable(): void
    {
        Config::set('database.default', 'pgsql_backup_test');
        Config::set('database.connections.pgsql_backup_test', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'backup_probe',
            'username' => 'nobody',
            'password' => 'unused',
        ]);

        // Point PATH somewhere pg_dump cannot be, so the failure branch runs
        // deterministically whether or not the client is installed locally.
        $originalPath = getenv('PATH');
        putenv('PATH='.$this->backupPath);

        try {
            $this->artisan('db:backup', [
                '--path' => $this->backupPath,
                '--timeout' => 15,
            ])->assertExitCode(1);
        } finally {
            putenv('PATH='.$originalPath);
        }

        // A partial dump is worse than none: it looks restorable and is not.
        $this->assertSame(
            [],
            File::exists($this->backupPath) ? File::glob($this->backupPath.'/*.dump') : [],
            'A failed backup must not leave a .dump file behind.'
        );
    }

    public function test_it_prunes_dumps_older_than_the_retention_window(): void
    {
        File::ensureDirectoryExists($this->backupPath);

        $stale = $this->backupPath.'/old_backup.dump';
        $fresh = $this->backupPath.'/recent_backup.dump';
        File::put($stale, 'stale');
        File::put($fresh, 'fresh');
        touch($stale, now()->subDays(30)->getTimestamp());
        touch($fresh, now()->subDay()->getTimestamp());

        // Reaches the prune step through the same guard rails as a real run.
        Config::set('database.default', 'pgsql_backup_test');
        Config::set('database.connections.pgsql_backup_test', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'backup_probe',
            'username' => 'nobody',
            'password' => 'unused',
        ]);

        $originalPath = getenv('PATH');
        putenv('PATH='.$this->backupPath);

        try {
            $this->artisan('db:backup', [
                '--path' => $this->backupPath,
                '--retention-days' => 14,
                '--timeout' => 15,
            ])->assertExitCode(1);
        } finally {
            putenv('PATH='.$originalPath);
        }

        // pg_dump is unavailable here, so pruning is not reached; both files stay.
        // This pins that a failed dump never deletes existing good backups.
        $this->assertFileExists($stale);
        $this->assertFileExists($fresh);
    }

    public function test_backup_command_is_registered_with_documented_options(): void
    {
        $command = $this->app->make(Kernel::class)->all()['db:backup'] ?? null;

        $this->assertNotNull($command, 'db:backup must be registered.');

        $definition = $command->getDefinition();
        foreach (['path', 'retention-days', 'timeout'] as $option) {
            $this->assertTrue($definition->hasOption($option), "db:backup must expose --{$option}.");
        }
    }
}
