<?php

namespace App\Console\Commands;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodService;
use App\Application\FixedAssets\FixedAssetCapitalizationService;
use App\Application\FixedAssets\FixedAssetDisposalPostingService;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDisposal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FixedAssetDisposalStressCommand extends Command
{
    protected $signature = 'accounting:fixed-asset-disposal-stress {--workers=50 : Number of concurrent workers}';

    protected $description = 'Stress test fixed asset disposal posting under high concurrency';

    public function handle(FixedAssetDisposalPostingService $disposalService, PeriodService $periodService, FixedAssetCapitalizationService $capService): int
    {
        $workersCount = (int) $this->option('workers');
        $dbDriver = DB::getDriverName();

        $this->info("Running Fixed Asset Disposal Concurrency Stress Test on DB driver: [{$dbDriver}] with [{$workersCount}] workers..");

        $user = User::query()->first() ?? User::factory()->create();

        // Setup test data in clean state
        $year = random_int(3000, 8999);
        while (FiscalYear::query()->where('year', $year)->exists()) {
            $year = random_int(3000, 8999);
        }

        $fiscalYear = $periodService->createFiscalYear($year, "{$year}-01-01", "{$year}-12-31");
        /** @var FinancialPeriod $period */
        $period = $fiscalYear->periods()->orderBy('month')->first();

        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        $group = AccountGroup::query()->firstOrCreate(
            ['code' => 'STRESS-GRP-DISP'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Disposal Stress Group', 'ar' => 'مجموعة ضغط استبعاد'],
                'type' => 'asset',
            ]
        );

        $costAccount = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => '1500-STRESS-COST-'.$year,
            'name' => ['en' => 'Fixed Asset Cost', 'ar' => 'تكلفة أصل'],
            'type' => 'asset',
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

        $lossAccount = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => '5900-STRESS-LOSS-'.$year,
            'name' => ['en' => 'Loss on Disposal', 'ar' => 'خسائر استبعاد'],
            'type' => 'expense',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $gainAccount = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => '4900-STRESS-GAIN-'.$year,
            'name' => ['en' => 'Gain on Disposal', 'ar' => 'أرباح استبعاد'],
            'type' => 'revenue',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $clearingAccount = Account::query()->create([
            'id' => (string) Str::uuid(),
            'code' => '1599-STRESS-CLEARING-'.$year,
            'name' => ['en' => 'Disposal Clearing', 'ar' => 'وسيط استبعاد'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $mappingService->setMapping('fixed_asset_cost', $costAccount->id);
        $mappingService->setMapping('accumulated_depreciation', $accumAccount->id);
        $mappingService->setMapping('fixed_asset_disposal_loss', $lossAccount->id);
        $mappingService->setMapping('fixed_asset_disposal_gain', $gainAccount->id);
        $mappingService->setMapping('fixed_asset_clearing', $clearingAccount->id);

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
            'asset_number' => "FA-STRESS-DISP-{$year}",
            'name' => ['en' => 'Stress Asset', 'ar' => 'أصل ضغط'],
            'fixed_asset_category_id' => $category->id,
            'currency' => 'EGP',
            'acquisition_date' => $period->start_date,
            'in_service_date' => $period->start_date,
            'cost_minor' => 1000000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);

        $capService->capitalize($asset->id, 'manual_capitalization', $period->start_date, $user->id);

        $assetId = $asset->id;
        $disposalDate = $period->end_date;
        $userId = $user->id;

        $tasks = [];
        for ($i = 0; $i < $workersCount; $i++) {
            $tasks[] = static function () use ($assetId, $disposalDate, $userId): array {
                /** @var FixedAssetDisposalPostingService $service */
                $service = app(FixedAssetDisposalPostingService::class);

                try {
                    $disposal = $service->postDisposal(
                        fixedAssetId: $assetId,
                        disposalDate: $disposalDate,
                        disposalType: 'scrap',
                        proceedsMinor: 0,
                        userId: $userId
                    );

                    return ['status' => 'success', 'disposal_id' => $disposal->id];
                } catch (ValidationException $e) {
                    return ['status' => 'validation_error', 'message' => $e->getMessage()];
                } catch (Throwable $e) {
                    return ['status' => 'error', 'message' => $e->getMessage()];
                }
            };
        }

        $results = Concurrency::run($tasks);

        $disposalsCount = FixedAssetDisposal::query()->where('fixed_asset_id', $assetId)->count();
        $asset->refresh();

        $this->info("Results: [{$disposalsCount}] durable disposal records created.");
        $this->info("Asset status: [{$asset->status}].");

        if ($disposalsCount !== 1) {
            $this->error("STRESS TEST FAILED: Expected exactly 1 disposal record, found {$disposalsCount}.");

            return 1;
        }

        if ($asset->status !== 'disposed') {
            $this->error("STRESS TEST FAILED: Expected asset status to be 'disposed', found '{$asset->status}'.");

            return 1;
        }

        $this->info('Fixed Asset Disposal Concurrency Stress Test PASSED CLEANLY!');

        return 0;
    }
}
