<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Process\Process;

/**
 * Takes a compressed PostgreSQL logical backup and prunes old ones.
 *
 * `spec/BACKUP_RESTORE_DRILL.md` specifies pg_dump as the mechanism under every
 * candidate retention option, but nothing executed it — the runbook described a
 * manual procedure. This command makes it schedulable and verifiable.
 *
 * The database password is passed through PGPASSWORD in the child process
 * environment, never on the command line where it would appear in `ps` output.
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
        {--path= : Directory to write into; defaults to storage/app/backups}
        {--retention-days=14 : Delete backups older than this many days; 0 disables pruning}
        {--timeout=1800 : Seconds to allow pg_dump before aborting}';

    protected $description = 'Create a compressed PostgreSQL dump and prune backups past the retention window.';

    public function handle(): int
    {
        $connectionName = Config::get('database.default');
        $connection = Config::get("database.connections.{$connectionName}");

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'pgsql') {
            $this->error("db:backup supports PostgreSQL only; the [{$connectionName}] connection is not pgsql.");

            return SymfonyCommand::FAILURE;
        }

        $directory = $this->resolveDirectory();

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            $this->error("Could not create backup directory [{$directory}].");

            return SymfonyCommand::FAILURE;
        }

        $database = (string) ($connection['database'] ?? '');
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $target = rtrim($directory, '/\\').DIRECTORY_SEPARATOR."{$database}_{$timestamp}.dump";

        $process = new Process(
            [
                'pg_dump',
                '--host='.($connection['host'] ?? '127.0.0.1'),
                '--port='.($connection['port'] ?? 5432),
                '--username='.($connection['username'] ?? ''),
                '--dbname='.$database,
                // Custom format: compressed, and restorable selectively with pg_restore.
                '--format=custom',
                '--no-password',
                '--file='.$target,
            ],
            env: ['PGPASSWORD' => (string) ($connection['password'] ?? '')],
            timeout: (float) $this->option('timeout'),
        );

        $this->line("Backing up [{$database}] to {$target}");

        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                $this->line(rtrim($buffer));
            }
        });

        if (! $process->isSuccessful()) {
            $this->error('pg_dump failed. Is the PostgreSQL client installed and on PATH?');

            // A partial file is worse than none: it looks like a usable backup.
            if (is_file($target)) {
                @unlink($target);
            }

            return SymfonyCommand::FAILURE;
        }

        $bytes = is_file($target) ? (int) filesize($target) : 0;

        if ($bytes === 0) {
            $this->error('pg_dump reported success but produced an empty file.');
            @unlink($target);

            return SymfonyCommand::FAILURE;
        }

        $this->info(sprintf('Backup complete: %s (%s).', basename($target), $this->humanBytes($bytes)));

        $pruned = $this->pruneExpiredBackups($directory);

        if ($pruned > 0) {
            $this->line("Pruned {$pruned} backup(s) past the retention window.");
        }

        return SymfonyCommand::SUCCESS;
    }

    private function resolveDirectory(): string
    {
        $path = (string) ($this->option('path') ?: '');

        return $path !== '' ? $path : storage_path('app/backups');
    }

    private function pruneExpiredBackups(string $directory): int
    {
        $retentionDays = (int) $this->option('retention-days');

        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = Carbon::now()->subDays($retentionDays)->getTimestamp();
        $pruned = 0;

        foreach ((array) glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'*.dump') as $file) {
            if (! is_string($file) || ! is_file($file)) {
                continue;
            }

            if ((int) filemtime($file) < $cutoff) {
                @unlink($file);
                $pruned++;
            }
        }

        return $pruned;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
