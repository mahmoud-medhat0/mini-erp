<?php

namespace App\Console\Commands;

use App\Application\Accounting\BankReconciliationService;
use App\Application\Support\BaseCurrencyResolver;
use App\Console\Commands\Concerns\ResolvesStressCurrency;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankReconciliationConcurrencyStressCommand extends Command
{
    use ResolvesStressCurrency;

    protected $signature = 'accounting:bank-reconciliation-concurrency-stress {--workers=50}';

    protected $description = 'Run PostgreSQL Bank Reconciliation concurrency stress test';

    public function handle(BankReconciliationService $reconService, BaseCurrencyResolver $baseCurrencyResolver): int
    {
        $driver = DB::connection()->getDriverName();
        $workerCount = (int) $this->option('workers');

        $this->info("Running Bank Reconciliation Concurrency Stress Test on DB driver: [{$driver}] with [{$workerCount}] workers..");

        // Setup baseline models inside transaction
        $fixture = DB::transaction(function () use ($baseCurrencyResolver) {
            $currency = $this->resolveStressCurrency($baseCurrencyResolver);
            $user = User::factory()->create();

            $stressYear = (FiscalYear::query()->max('year') ?? 2100) + 1;

            $year = FiscalYear::query()->create([
                'year' => $stressYear,
                'name' => 'FY-ReconStress-'.$stressYear,
                'start_date' => "{$stressYear}-01-01",
                'end_date' => "{$stressYear}-12-31",
                'status' => 'open',
            ]);

            $period = FinancialPeriod::query()->create([
                'fiscal_year_id' => $year->id,
                'month' => 1,
                'start_date' => "{$stressYear}-01-01",
                'end_date' => "{$stressYear}-01-31",
                'status' => 'open',
            ]);

            $bankGlAccount = Account::query()->create([
                'code' => '1020-RECON-STRESS-'.Str::random(4),
                'name' => 'Bank Stress GL Account',
                'type' => 'asset',
                'nature' => 'debit',
                'currency' => $currency,
                'is_active' => true,
            ]);

            $bankAccount = BankAccount::query()->create([
                'code' => 'BANK-STRESS-'.Str::random(4),
                'name' => 'Stress Bank Main',
                'account_number' => 'ACC-STRESS-'.Str::random(6),
                'bank_name' => 'Stress Bank',
                'currency' => $currency,
                'gl_account_id' => $bankGlAccount->id,
                'is_active' => true,
            ]);

            // Create Journal and Ledger Entry for Bank
            $journal = JournalEntry::query()->create([
                'number' => 'JV-RECON-'.Str::random(6),
                'financial_period_id' => $period->id,
                'entry_date' => "{$stressYear}-01-15",
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
                'description' => 'Recon Stress Test Deposit',
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $jLine = JournalLine::query()->create([
                'journal_entry_id' => $journal->id,
                'line_no' => 1,
                'account_id' => $bankGlAccount->id,
                'debit_minor' => 500000,
                'credit_minor' => 0,
                'currency' => $currency,
                'fx_rate_e6' => 1000000,
                'debit_txn_minor' => 500000,
                'credit_txn_minor' => 0,
            ]);

            $ledgerEntry = LedgerEntry::query()->create([
                'journal_entry_id' => $journal->id,
                'journal_line_id' => $jLine->id,
                'account_id' => $bankGlAccount->id,
                'financial_period_id' => $period->id,
                'entry_date' => "{$stressYear}-01-15",
                'currency' => $currency,
                'debit_minor' => 500000,
                'credit_minor' => 0,
                'fx_rate_e6' => 1000000,
                'debit_txn_minor' => 500000,
                'credit_txn_minor' => 0,
            ]);

            return [
                'user_id' => $user->id,
                'stress_year' => $stressYear,
                'period_id' => $period->id,
                'bank_account_id' => $bankAccount->id,
                'ledger_entry_id' => $ledgerEntry->id,
            ];
        });

        $actorId = $fixture['user_id'];
        $stressYear = $fixture['stress_year'];

        // Scenario 1: Duplicate Match Pressure
        $this->info("Simulating {$workerCount} concurrent match attempts for the same candidate ledger entry..");

        $recon1 = $reconService->createDraft([
            'bank_account_id' => $fixture['bank_account_id'],
            'financial_period_id' => $fixture['period_id'],
            'date_from' => "{$stressYear}-01-01",
            'date_to' => "{$stressYear}-01-31",
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $actorId);

        $line1 = $reconService->addLine($recon1->id, [
            'statement_date' => "{$stressYear}-01-15",
            'debit_minor' => 500000,
            'credit_minor' => 0,
            'description' => 'Line 1',
        ], $actorId);

        $recon2 = $reconService->createDraft([
            'bank_account_id' => $fixture['bank_account_id'],
            'financial_period_id' => $fixture['period_id'],
            'date_from' => "{$stressYear}-01-01",
            'date_to' => "{$stressYear}-01-31",
            'statement_opening_balance_minor' => 0,
            'statement_closing_balance_minor' => 500000,
        ], $actorId);

        $line2 = $reconService->addLine($recon2->id, [
            'statement_date' => "{$stressYear}-01-15",
            'debit_minor' => 500000,
            'credit_minor' => 0,
            'description' => 'Line 2',
        ], $actorId);

        $tasks = [];
        for ($i = 0; $i < $workerCount; $i++) {
            $targetLineId = ($i % 2 === 0) ? $line1->id : $line2->id;
            $ledgerId = $fixture['ledger_entry_id'];

            $tasks[] = function () use ($targetLineId, $ledgerId, $actorId) {
                /** @var BankReconciliationService $service */
                $service = app(BankReconciliationService::class);
                try {
                    $service->matchLine($targetLineId, $ledgerId, $actorId);

                    return ['status' => 'matched', 'line_id' => $targetLineId];
                } catch (\Throwable $e) {
                    return ['status' => 'rejected', 'error' => $e->getMessage()];
                }
            };
        }

        $matchResults = Concurrency::run($tasks);

        $matchedCount = BankReconciliationLine::query()
            ->where('matched_ledger_entry_id', $fixture['ledger_entry_id'])
            ->where('status', 'matched')
            ->count();

        if ($matchedCount !== 1) {
            $this->error("FAILED: Duplicate ledger entry matches detected! Count: {$matchedCount}");

            return 1;
        }

        $this->info('PASS: Exactly 1 statement line matched candidate ledger entry under high concurrency.');

        // Scenario 2: Finalize Replay Pressure
        $this->info("Simulating {$workerCount} concurrent finalize requests with shared idempotency key..");

        $matchedLineRow = BankReconciliationLine::query()
            ->where('matched_ledger_entry_id', $fixture['ledger_entry_id'])
            ->where('status', 'matched')
            ->firstOrFail();

        $reconId = $matchedLineRow->bank_reconciliation_id;
        $idempotencyKey = 'recon_finalize_stress_'.Str::random(6);

        $finalizeTasks = [];
        for ($i = 0; $i < $workerCount; $i++) {
            $key = $idempotencyKey;

            $finalizeTasks[] = function () use ($reconId, $actorId, $key) {
                /** @var BankReconciliationService $service */
                $service = app(BankReconciliationService::class);
                try {
                    $res = $service->finalize($reconId, $actorId, $key);

                    return ['status' => $res->status];
                } catch (\Throwable $e) {
                    return ['status' => 'error', 'message' => $e->getMessage()];
                }
            };
        }

        $finalizeResults = Concurrency::run($finalizeTasks);

        $finalRecon = BankReconciliation::query()->find($reconId);
        if ($finalRecon->status !== 'reconciled') {
            $this->error("FAILED: Finalize stress test failed. Status: {$finalRecon->status}");

            return 1;
        }

        $this->info("PASS: Bank Reconciliation status: {$finalRecon->status}. Idempotent finalization under concurrent pressure succeeded.");

        $this->info('Bank Reconciliation Concurrency Stress Test PASSED CLEANLY.');

        return 0;
    }
}
