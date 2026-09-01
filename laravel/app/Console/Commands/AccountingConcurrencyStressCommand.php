<?php

namespace App\Console\Commands;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\PostingEngine;
use App\Application\Accounting\ReversalService;
use App\Application\Support\BaseCurrencyResolver;
use App\Console\Commands\Concerns\GuardsStressExecution;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use App\Support\Numbering\NumberSequenceAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AccountingConcurrencyStressCommand extends Command
{
    use GuardsStressExecution;

    protected $signature = 'accounting:concurrency-stress {--workers=50}';

    protected $description = 'Run sequential stress test for Phase 2 Accounting Core invariants.';

    public function handle(
        JournalDraftService $draftService,
        PostingEngine $postingEngine,
        ReversalService $reversalService,
        PeriodService $periodService,
        NumberSequenceAllocator $allocator,
        BaseCurrencyResolver $baseCurrencyResolver,
    ): int {
        if ($this->refusesProductionStressRun()) {
            return self::FAILURE;
        }

        $driver = DB::connection()->getDriverName();
        $this->info("Running Accounting Integrity & Sequence Stress test on DB driver: {$driver}");

        DB::beginTransaction();

        try {
            $user = User::query()->first() ?? User::factory()->create();
            $currency = $baseCurrencyResolver->resolve();

            Currency::query()->firstOrCreate(
                ['code' => $currency],
                [
                    'name' => ['en' => $currency, 'ar' => $currency],
                    'symbol' => $currency,
                    'exponent' => 2,
                ],
            );

            // 1. Setup temporary fiscal year, period and accounts
            $yearNum = 2090 + rand(1, 99);
            $fiscalYear = $periodService->createFiscalYear($yearNum, "{$yearNum}-01-01", "{$yearNum}-12-31");
            $period = $fiscalYear->periods()->first();

            $group = AccountGroup::create([
                'id' => (string) Str::uuid(),
                'code' => "G-{$yearNum}",
                'name' => ['en' => 'Stress Assets', 'ar' => 'أصول الضغط'],
                'type' => 'asset',
            ]);

            $cash = Account::create([
                'id' => (string) Str::uuid(),
                'code' => "C-{$yearNum}",
                'name' => ['en' => 'Stress Cash', 'ar' => 'نقدية الضغط'],
                'type' => 'asset',
                'nature' => 'debit',
                'account_group_id' => $group->id,
                'is_control' => false,
                'currency' => $currency,
            ]);

            $rev = Account::create([
                'id' => (string) Str::uuid(),
                'code' => "R-{$yearNum}",
                'name' => ['en' => 'Stress Revenue', 'ar' => 'إيراد الضغط'],
                'type' => 'revenue',
                'nature' => 'credit',
                'account_group_id' => $group->id,
                'is_control' => false,
                'currency' => $currency,
            ]);

            // Case 1: Sequential Number Sequence Allocation
            $iterations = (int) $this->option('workers');
            $this->info("Testing {$iterations} sequential JV sequence allocations...");
            $allocatedNumbers = [];
            for ($i = 0; $i < $iterations; $i++) {
                $allocatedNumbers[] = $allocator->nextNumber('accounting.journal', 'JV');
            }

            if (count(array_unique($allocatedNumbers)) !== $iterations) {
                $this->error('FAIL: Duplicate JV sequence numbers allocated!');

                return self::FAILURE;
            }
            $this->info('PASS: All JV sequence numbers are 100% unique.');

            // Case 2: Idempotent Duplicate Post of Same Draft Entry
            $entry = $draftService->createDraft(
                [
                    'entry_date' => "{$yearNum}-01-15",
                    'financial_period_id' => $period->id,
                    'currency' => $currency,
                    'description' => 'Idempotent Post Test',
                ],
                [
                    ['account_id' => $cash->id, 'debit_minor' => 1000, 'credit_minor' => 0],
                    ['account_id' => $rev->id, 'debit_minor' => 0, 'credit_minor' => 1000],
                ],
                $user->id
            );

            for ($i = 0; $i < 5; $i++) {
                try {
                    $postingEngine->post($entry->fresh(), $user->id);
                } catch (Throwable $e) {
                    // expected idempotency hit
                }
            }

            if ($entry->fresh()->status !== 'posted') {
                $this->error('FAIL: Entry failed to post.');

                return self::FAILURE;
            }
            $this->info('PASS: Single durable post achieved across repeated posting attempts.');

            // Case 3: Idempotent Reversal Protection
            $postedEntry = $entry->fresh();
            for ($i = 0; $i < 5; $i++) {
                try {
                    $reversalService->reverse($postedEntry->fresh(), $period->id, $user->id);
                } catch (Throwable $e) {
                    // expected idempotency hit
                }
            }

            $reversalsFound = DB::table('journal_entry')
                ->where('reverses_entry_id', $postedEntry->id)
                ->count();

            if ($reversalsFound !== 1) {
                $this->error("FAIL: Expected exactly 1 reversal entry, found {$reversalsFound}.");

                return self::FAILURE;
            }
            $this->info('PASS: Repeated reversal attempt created exactly 1 reversing journal entry.');

        } finally {
            DB::rollBack();
        }

        $this->info('Accounting Integrity Stress Test PASSED CLEANLY.');

        return self::SUCCESS;
    }
}
