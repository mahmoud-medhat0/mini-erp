<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Process\Process;

class LocalVerificationGateCommand extends Command
{
    protected $signature = 'qa:verify-local
        {--feature-files : Run each tests/Feature/*Test.php file individually after configured suites}
        {--only-feature-files : Skip configured suites and run Feature files only}
        {--filter= : Only run Feature files whose filename contains this text}
        {--stop-on-failure : Stop immediately when a command fails}
        {--timeout=900 : Per-command timeout in seconds}';

    protected $description = 'Run local verification with visible progress, avoiding silent monolithic PHPUnit runs.';

    public function handle(): int
    {
        $timeout = max(60, (int) $this->option('timeout'));
        $stopOnFailure = (bool) $this->option('stop-on-failure');
        $results = [];
        $startedAt = microtime(true);

        if (! $this->option('only-feature-files')) {
            foreach (['Unit', 'Integration', 'Invariants', 'Concurrency'] as $suite) {
                $result = $this->runStep(
                    label: "testsuite={$suite}",
                    arguments: ['test', "--testsuite={$suite}"],
                    timeout: $timeout,
                );
                $results[] = $result;

                if (! $result['passed'] && $stopOnFailure) {
                    return $this->finish($results, $startedAt);
                }
            }
        }

        if ($this->option('feature-files') || $this->option('only-feature-files')) {
            foreach ($this->featureFiles() as $featureFile) {
                $result = $this->runStep(
                    label: $featureFile,
                    arguments: ['test', $featureFile],
                    timeout: $timeout,
                );
                $results[] = $result;

                if (! $result['passed'] && $stopOnFailure) {
                    return $this->finish($results, $startedAt);
                }
            }
        }

        return $this->finish($results, $startedAt);
    }

    /**
     * @return list<string>
     */
    private function featureFiles(): array
    {
        $filter = (string) ($this->option('filter') ?? '');
        $files = glob(base_path('tests/Feature/*Test.php')) ?: [];
        sort($files);

        $relative = [];
        foreach ($files as $file) {
            $path = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

            if ($filter !== '' && ! str_contains(basename($path), $filter)) {
                continue;
            }

            $relative[] = $path;
        }

        if ($relative === []) {
            $this->warn('No Feature test files matched the requested filter.');
        }

        return $relative;
    }

    /**
     * @param  list<string>  $arguments
     * @return array{label: string, passed: bool, exit_code: int, duration_ms: int}
     */
    private function runStep(string $label, array $arguments, int $timeout): array
    {
        $this->newLine();
        $this->line("==> {$label}");

        $startedAt = microtime(true);
        $process = new Process(
            array_merge([PHP_BINARY, 'artisan'], $arguments),
            base_path(),
            [
                'APP_ENV' => 'testing',
                'BCRYPT_ROUNDS' => '4',
                'CACHE_STORE' => 'array',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'DB_URL' => '',
                'MAIL_MAILER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'SESSION_DRIVER' => 'array',
            ],
            timeout: $timeout,
        );
        $exitCode = $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $passed = $exitCode === SymfonyCommand::SUCCESS;
        $status = $passed ? 'PASS' : 'FAIL';

        $this->line("{$status}: {$label} ({$durationMs} ms)");

        return [
            'label' => $label,
            'passed' => $passed,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * @param  list<array{label: string, passed: bool, exit_code: int, duration_ms: int}>  $results
     */
    private function finish(array $results, float $startedAt): int
    {
        $this->newLine();
        $this->line('Verification summary');
        $this->table(
            ['Status', 'Step', 'Exit', 'Duration ms'],
            array_map(
                static fn (array $result): array => [
                    $result['passed'] ? 'PASS' : 'FAIL',
                    $result['label'],
                    $result['exit_code'],
                    $result['duration_ms'],
                ],
                $results,
            ),
        );

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $failed = array_values(array_filter($results, static fn (array $result): bool => ! $result['passed']));

        if ($failed !== []) {
            $this->error('Local verification failed. First failing step: '.$failed[0]['label']);

            return SymfonyCommand::FAILURE;
        }

        $this->info("Local verification passed in {$durationMs} ms.");

        return SymfonyCommand::SUCCESS;
    }
}
