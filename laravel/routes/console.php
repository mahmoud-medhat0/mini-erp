<?php

use App\Support\Auth\AuthGarbageCollector;
use App\Support\Concurrency\DatabaseIdempotencyStore;
use App\Support\Concurrency\DuplicateOperationInProgressException;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tokens:gc {--batch=500 : Maximum rows to delete per table}', function (AuthGarbageCollector $collector) {
    $summary = $collector->collect((int) $this->option('batch'));

    $this->info(sprintf(
        'Deleted sessions=%d password_reset_tokens=%d idempotency_keys=%d',
        $summary['sessions'],
        $summary['password_reset_tokens'],
        $summary['idempotency_keys'],
    ));

    return Command::SUCCESS;
})->purpose('Delete expired sessions, password reset tokens, and idempotency keys in bounded batches');

Artisan::command('concurrency:stress {--workers=100 : Number of concurrent operations}', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->error('concurrency:stress requires PostgreSQL because the production allocator relies on ON CONFLICT locking.');

        return Command::FAILURE;
    }

    $workers = max(1, min((int) $this->option('workers'), 250));
    $companyId = (string) Str::uuid();
    $sequenceKey = 'stress-'.Str::lower(Str::random(10));
    $operation = 'stress.idempotency.'.Str::lower(Str::random(10));
    $idempotencyKey = (string) Str::uuid();

    DB::table('company')->insert([
        'id' => $companyId,
        'name' => json_encode(['en' => 'Concurrency Stress', 'ar' => 'اختبار التزامن'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        $sequenceTasks = [];

        for ($i = 0; $i < $workers; $i++) {
            $sequenceTasks[] = static fn (): int => app(NumberSequenceAllocator::class)->nextValue($companyId, $sequenceKey);
        }

        $values = Concurrency::run($sequenceTasks);

        sort($values);

        if (count(array_unique($values)) !== $workers || $values[0] !== 1 || $values[$workers - 1] !== $workers) {
            $this->error('Number sequence stress failed: duplicate or missing values detected.');

            return Command::FAILURE;
        }

        $idempotencyTasks = [];

        for ($i = 0; $i < $workers; $i++) {
            $idempotencyTasks[] = static fn (): string => (static function () use ($operation, $idempotencyKey) {
                try {
                    $result = app(DatabaseIdempotencyStore::class)->run(
                        $operation,
                        $idempotencyKey,
                        static function () {
                            usleep(250000);

                            return ['ok' => true, 'pid' => getmypid()];
                        },
                        null,
                        'stress-request',
                        now()->addMinutes(10),
                    );

                    return $result->executed ? 'executed' : 'replayed';
                } catch (DuplicateOperationInProgressException) {
                    return 'pending';
                }
            })();
        }

        $idempotencyResults = Concurrency::run($idempotencyTasks);

        if (count(array_filter($idempotencyResults, fn (string $status): bool => $status === 'executed')) !== 1) {
            $this->error('Idempotency stress failed: callback did not execute exactly once.');

            return Command::FAILURE;
        }

        $this->info("Concurrency stress passed with {$workers} workers.");
        $this->info('Number sequence values are unique and contiguous.');
        $this->info('Idempotency callback executed exactly once.');

        return Command::SUCCESS;
    } finally {
        DB::table('number_sequence')
            ->where('company_id', $companyId)
            ->where('key', $sequenceKey)
            ->delete();
        DB::table('idempotency_keys')
            ->where('operation', $operation)
            ->delete();
        DB::table('company')
            ->where('id', $companyId)
            ->delete();
    }
})->purpose('Run PostgreSQL concurrency stress checks for numbering and idempotency');
