<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodService;
use App\Application\FixedAssets\FixedAssetDepreciationEngineService;
use App\Application\FixedAssets\FixedAssetDepreciationPostingService;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FixedAssetDepreciationStressCommand extends Command
{
    protected $signature = 'accounting:fixed-asset-depreciation-stress {--workers=50 : Number of concurrent workers}';

    protected $description = 'Stress test fixed asset depreciation run posting under high concurrency';

    public function handle(FixedAssetDepreciationEngineService $engineService, FixedAssetDepreciationPostingService $postingService, PeriodService $periodService): int
    {
        $workersCount = (int) $this->option('workers');
        $dbDriver = DB::getDriverName();

        $this->info("Running Fixed Asset Depreciation Concurrency Stress Test on DB driver: [{$dbDriver}] with [{$workersCount}] workers..");

        $user = User::query()->first() ?? User::factory()->create();

        // Setup test data in clean state
        $year = random_int(3000, 8999);
        while (FiscalYear::query()->where('year', $year)->exists()) {
            $year = random_int(3000, 8999);
        }

        $fiscalYear = $periodService->createFiscalYear($year, "{$year}-01-01", "{$year}-12-31");
        /** @var FinancialPeriod $period */
        $period = $fiscalYear->periods()->orderBy('month')->first();

        // Map GL accounts for depreciation
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        $group = AccountGroup::query()->firstOrCreate(
            ['code' => 'STRESS-GRP'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Stress Group', 'ar' => 'مجموعة ضغط'],
                'type' => 'expense',
            ]
        );

        $expAccount = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => '6000-STRESS-DEP-'.$year,
            'name' => ['en' => 'Depreciation Expense', 'ar' => 'مصروف إهلاك'],
            'type' => 'expense',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $accumAccount = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => '1550-STRESS-ACCUM-'.$year,
            'name' => ['en' => 'Accumulated Depreciation', 'ar' => 'مجمع إهلاك'],
            'type' => 'asset',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $mappingService->setMapping('depreciation_expense', $expAccount->id);
        $mappingService->setMapping('accumulated_depreciation', $accumAccount->id);

        // Create asset category & active asset
        $category = FixedAssetCategory::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'STRESS-CAT-'.$year,
            'name' => ['en' => 'Stress Category', 'ar' => 'فئة ضغط'],
            'useful_life_months' => 12,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        $asset = FixedAsset::query()->create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-STRESS-'.$year.'-001',
            'name' => ['en' => 'Stress Asset', 'ar' => 'أصل ضغط'],
            'fixed_asset_category_id' => $category->id,
            'currency' => 'EGP',
            'acquisition_date' => "{$year}-01-01",
            'in_service_date' => "{$year}-01-01",
            'cost_minor' => 1200000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
        ]);

        // Generate schedule
        $schedules = $engineService->generateSchedule($asset->id);
        /** @var FixedAssetDepreciationSchedule $firstSchedule */
        $firstSchedule = $schedules->first();

        /** @var FinancialPeriod $period */
        $period = FinancialPeriod::query()->where('id', $firstSchedule->financial_period_id)->firstOrFail();

        $idempotencyKey = 'stress_depreciation_run_'.$period->id;

        $tasks = [];
        for ($i = 1; $i <= $workersCount; $i++) {
            $tasks[] = static fn (): string => (static function () use ($period, $idempotencyKey, $user): string {
                try {
                    $run = app(FixedAssetDepreciationPostingService::class)->postDepreciationRun(
                        $period->id,
                        $user->id,
                        $idempotencyKey
                    );

                    return 'run:'.$run->id;
                } catch (ValidationException) {
                    return 'rejected';
                } catch (Throwable $e) {
                    if (str_contains($e->getMessage(), 'already being processed')) {
                        return 'rejected';
                    }

                    return 'error: '.$e->getMessage();
                }
            })();
        }

        $results = Concurrency::run($tasks);

        $rejectedCount = 0;
        $runIds = [];
        foreach ($results as $res) {
            if (str_starts_with($res, 'run:')) {
                $runIds[] = substr($res, 4);
            } else {
                $rejectedCount++;
            }
        }

        $uniqueRunCount = count(array_unique($runIds));
        $this->info('Worker execution results: completed_or_replayed='.count($runIds).", unique_runs={$uniqueRunCount}, rejected_or_deduped={$rejectedCount}");

        // Assertions
        $runs = FixedAssetDepreciationRun::query()->where('financial_period_id', $period->id)->get();
        if ($runs->count() !== 1) {
            $this->error("FAIL: Expected exactly 1 depreciation run, found [{$runs->count()}].");

            return self::FAILURE;
        }
        $this->info('PASS: Exactly 1 durable depreciation run created.');

        $postedSchedules = FixedAssetDepreciationSchedule::query()
            ->where('financial_period_id', $period->id)
            ->where('status', 'posted')
            ->get();

        if ($postedSchedules->count() !== 1) {
            $this->error("FAIL: Expected exactly 1 posted schedule line for asset in period, found [{$postedSchedules->count()}].");

            return self::FAILURE;
        }
        $this->info('PASS: Zero duplicate schedule row postings.');

        /** @var FixedAssetDepreciationRun $run */
        $run = $runs->first();
        /** @var JournalEntry $journal */
        $journal = JournalEntry::query()->findOrFail($run->journal_entry_id);

        $journalTotal = (int) DB::table('journal_line')->where('journal_entry_id', $journal->id)->sum('debit_minor');
        if ($journalTotal !== $run->total_depreciation_minor) {
            $this->error("FAIL: Journal total [{$journalTotal}] does not match run total [{$run->total_depreciation_minor}].");

            return self::FAILURE;
        }
        $this->info('PASS: Journal voucher totals match depreciation run totals.');

        $this->info('Fixed Asset Depreciation Stress Test PASSED CLEANLY.');

        return self::SUCCESS;
    }
}
