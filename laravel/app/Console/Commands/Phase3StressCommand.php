<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class Phase3StressCommand extends Command
{
    protected $signature = 'accounting:phase3-stress {--workers=50}';

    protected $description = 'Run orchestrator stress test for Phase 3 workflows (Receipts, Payments, Allocations, Cheques, Bank Reconciliations, Reports, Integrity).';

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();
        $workers = max(2, min((int) $this->option('workers'), 250));

        $this->info('==========================================================================');
        $this->info("Starting Phase 3 Concurrency & Stress Orchestrator (DB: {$driver}, Workers: {$workers})");
        $this->info('==========================================================================');

        if ($driver !== 'pgsql') {
            $this->warn("DB driver is [{$driver}]. PostgreSQL is required for true multi-process row locking concurrency.");
            $this->warn('Running non-mutating integrity audit only on current database...');

            $exitCode = Artisan::call('accounting:phase3-integrity-check', [], $this->output);

            return $exitCode;
        }

        // 1. Run Accounting Concurrency Stress (Phase 2 Core)
        $this->info("\n--- Phase 2 Core Accounting Concurrency Stress ---");
        $res = Artisan::call('accounting:concurrency-stress', ['--workers' => $workers], $this->output);
        if ($res !== SymfonyCommand::SUCCESS) {
            $this->error('FAIL: Phase 2 Core Accounting Concurrency Stress failed.');

            return SymfonyCommand::FAILURE;
        }

        // 2. Run Allocation Concurrency Stress (AR/AP Allocations)
        $this->info("\n--- Phase 3 AR/AP Allocation Concurrency Stress ---");
        $res = Artisan::call('accounting:allocation-concurrency-stress', ['--workers' => $workers], $this->output);
        if ($res !== SymfonyCommand::SUCCESS) {
            $this->error('FAIL: Allocation Concurrency Stress failed.');

            return SymfonyCommand::FAILURE;
        }

        // 3. Run Cheque Lifecycle Concurrency Stress
        $this->info("\n--- Phase 3 Cheque Lifecycle Concurrency Stress ---");
        $res = Artisan::call('accounting:cheque-concurrency-stress', ['--workers' => $workers], $this->output);
        if ($res !== SymfonyCommand::SUCCESS) {
            $this->error('FAIL: Cheque Concurrency Stress failed.');

            return SymfonyCommand::FAILURE;
        }

        // 4. Run Bank Reconciliation Concurrency Stress
        $this->info("\n--- Phase 3 Bank Reconciliation Concurrency Stress ---");
        $res = Artisan::call('accounting:bank-reconciliation-concurrency-stress', ['--workers' => $workers], $this->output);
        if ($res !== SymfonyCommand::SUCCESS) {
            $this->error('FAIL: Bank Reconciliation Concurrency Stress failed.');

            return SymfonyCommand::FAILURE;
        }

        // 5. Run Final Phase 3 Data Integrity Check
        $this->info("\n--- Final Phase 3 Financial Invariants & Report Integrity Audit ---");
        $res = Artisan::call('accounting:phase3-integrity-check', [], $this->output);
        if ($res !== SymfonyCommand::SUCCESS) {
            $this->error('FAIL: Phase 3 Integrity Check failed after stress testing.');

            return SymfonyCommand::FAILURE;
        }

        $this->info("\n==========================================================================");
        $this->info('SUCCESS: All Phase 3 Concurrency & Stress Tests Completed with 100% Integrity.');
        $this->info('==========================================================================');

        return SymfonyCommand::SUCCESS;
    }
}
