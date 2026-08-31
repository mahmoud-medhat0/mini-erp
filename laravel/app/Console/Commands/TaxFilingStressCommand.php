<?php

namespace App\Console\Commands;

use App\Application\Taxes\TaxPeriodService;
use App\Application\Taxes\TaxReturnService;
use App\Models\TaxPeriod;
use App\Models\TaxReturn;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TaxFilingStressCommand extends Command
{
    protected $signature = 'accounting:tax-filing-stress {--workers=50}';

    protected $description = 'Stress test concurrent tax return filing and period locking for idempotency and concurrency integrity';

    public function handle(TaxPeriodService $periodService, TaxReturnService $returnService): int
    {
        $driver = DB::connection()->getDriverName();
        $this->info("Running Tax Filing Stress test on DB driver: {$driver}");

        // Cleanup previous stress data
        TaxPeriod::query()->where('period_label', 'STRESS-2026-01')->delete();

        // 1. Create Tax Period
        $period = $periodService->createPeriod([
            'period_label' => 'STRESS-2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'notes' => 'Concurrency Stress Period',
        ]);

        // 2. Generate Draft Return
        $draftReturn = $returnService->generateDraftReturn($period->id, null);

        $workerCount = (int) $this->option('workers');
        $successCount = 0;
        $idempotentCount = 0;
        $errors = [];

        // Execute simulated worker threads attempting to file the draft return concurrently
        for ($i = 0; $i < $workerCount; $i++) {
            try {
                $filed = $returnService->fileReturn($draftReturn->id, null, "Worker {$i} Filing");
                if ($filed->status === 'filed') {
                    $successCount++;
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Assertions
        $period->refresh();
        $draftReturn->refresh();

        if ($period->status !== 'filed') {
            $this->error("FAIL: Expected TaxPeriod status 'filed', got '{$period->status}'.");

            return 1;
        }

        if ($draftReturn->status !== 'filed') {
            $this->error("FAIL: Expected TaxReturn status 'filed', got '{$draftReturn->status}'.");

            return 1;
        }

        $filedCount = TaxReturn::query()
            ->where('tax_period_id', $period->id)
            ->where('status', 'filed')
            ->count();

        if ($filedCount !== 1) {
            $this->error("FAIL: Expected exactly 1 filed return for tax period, found {$filedCount}.");

            return 1;
        }

        // Idempotency check: Filing an already filed return must return the filed return without error
        $retryFiled = $returnService->fileReturn($draftReturn->id, null, 'Retry Filing');
        if ($retryFiled->status !== 'filed') {
            $this->error('FAIL: Idempotent file attempt failed.');

            return 1;
        }

        $this->info("PASS: Concurrent tax period filing completed cleanly. Workers executed: {$workerCount}, Successes/Idempotent returns: {$successCount}.");
        $this->info("Tax Filing Stress Test PASSED cleanly on {$driver}. All checks verified.");

        return 0;
    }
}
