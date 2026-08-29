<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodService;
use App\Application\FixedAssets\FixedAssetCapitalizationService;
use App\Application\FixedAssets\FixedAssetDepreciationEngineService;
use App\Application\FixedAssets\FixedAssetDepreciationPostingService;
use App\Application\FixedAssets\FixedAssetDisposalPostingService;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountingAccountMapping;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\FixedAssetDisposal;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase6Slice6FixedAssetDisposalTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    private FixedAssetCategory $category;

    private FixedAsset $asset;

    private FinancialPeriod $period;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function disposalConfirmation(array $payload = []): array
    {
        return array_merge($payload, [
            'confirm_action' => 'STORE_FIXED_ASSET_DISPOSAL',
            'reason' => 'Automated fixed asset disposal test approval.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function reverseDisposalConfirmation(array $payload = []): array
    {
        return array_merge($payload, [
            'confirm_action' => 'REVERSE_FIXED_ASSET_DISPOSAL',
            'reason' => 'Automated fixed asset disposal reversal test approval.',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create RBAC Permissions
        Permission::findOrCreate('fixedAssets.view', 'web');
        Permission::findOrCreate('fixedAssets.create', 'web');
        Permission::findOrCreate('fixedAssets.edit', 'web');
        Permission::findOrCreate('fixedAssets.post', 'web');
        Permission::findOrCreate('fixedAssets.reverse', 'web');
        Permission::findOrCreate('view_financials', 'web');

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo([
            'fixedAssets.view',
            'fixedAssets.create',
            'fixedAssets.edit',
            'fixedAssets.post',
            'fixedAssets.reverse',
            'view_financials',
        ]);

        $this->unauthorizedUser = User::factory()->create();

        // Setup GL accounts & mappings
        /** @var AccountingAccountMappingService $mappingService */
        $mappingService = app(AccountingAccountMappingService::class);

        $group = AccountGroup::create([
            'id' => (string) Str::uuid(),
            'code' => 'ASSET-GRP-DISP',
            'name' => ['en' => 'Fixed Assets Group', 'ar' => 'مجموعة أصول'],
            'type' => 'asset',
        ]);

        $costAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1500-COST-DISP',
            'name' => ['en' => 'Fixed Asset Cost', 'ar' => 'تكلفة أصل'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $accumAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1550-ACCUM-DISP',
            'name' => ['en' => 'Accumulated Depreciation', 'ar' => 'مجمع إهلاك'],
            'type' => 'asset',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $expAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '6000-DEP-EXP',
            'name' => ['en' => 'Depreciation Expense', 'ar' => 'مصروف إهلاك'],
            'type' => 'expense',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $clearingAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '1599-FA-CLEARING',
            'name' => ['en' => 'Fixed Asset Clearing', 'ar' => 'حساب وسيط أصول'],
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $gainAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '4900-DISP-GAIN',
            'name' => ['en' => 'Gain on Disposal', 'ar' => 'أرباح بيع أصول'],
            'type' => 'revenue',
            'nature' => 'credit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $lossAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '5900-DISP-LOSS',
            'name' => ['en' => 'Loss on Disposal', 'ar' => 'خسائر بيع أصول'],
            'type' => 'expense',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'is_control' => false,
            'is_active' => true,
        ]);

        $mappingService->setMapping('fixed_asset_cost', $costAccount->id);
        $mappingService->setMapping('accumulated_depreciation', $accumAccount->id);
        $mappingService->setMapping('depreciation_expense', $expAccount->id);
        $mappingService->setMapping('fixed_asset_clearing', $clearingAccount->id);
        $mappingService->setMapping('fixed_asset_disposal_gain', $gainAccount->id);
        $mappingService->setMapping('fixed_asset_disposal_loss', $lossAccount->id);

        // Setup Fiscal Year & Periods
        /** @var PeriodService $periodService */
        $periodService = app(PeriodService::class);
        $year = 2026;
        $fiscalYear = FiscalYear::where('year', $year)->first();
        if (! $fiscalYear) {
            $fiscalYear = $periodService->createFiscalYear($year, "{$year}-01-01", "{$year}-12-31");
        }

        // Setup Asset Category & Asset
        $this->category = FixedAssetCategory::create([
            'id' => (string) Str::uuid(),
            'code' => 'MACHINERY-DISP',
            'name' => ['en' => 'Machinery', 'ar' => 'آلات'],
            'useful_life_months' => 12,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        $this->asset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-DISP-001',
            'name' => ['en' => 'CNC Milling Lathe', 'ar' => 'ماكينة مخارط'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 1200000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);

        /** @var FixedAssetCapitalizationService $capService */
        $capService = app(FixedAssetCapitalizationService::class);
        $capService->capitalize($this->asset->id, 'manual_capitalization', '2026-01-15', $this->authorizedUser->id);

        /** @var FixedAssetDepreciationEngineService $engine */
        $engine = app(FixedAssetDepreciationEngineService::class);
        $schedules = $engine->generateSchedule($this->asset->id);

        /** @var FixedAssetDepreciationSchedule $firstSchedule */
        $firstSchedule = $schedules->first();
        $this->period = FinancialPeriod::where('id', $firstSchedule->financial_period_id)->firstOrFail();
    }

    public function test_scrap_disposal_posts_loss_equal_net_book_value(): void
    {
        // Post 2 periods of depreciation
        /** @var FixedAssetDepreciationPostingService $depPostingService */
        $depPostingService = app(FixedAssetDepreciationPostingService::class);
        $depPostingService->postDepreciationRun($this->period->id, $this->authorizedUser->id);

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/disposals", $this->disposalConfirmation([
                'disposal_date' => $this->period->end_date,
                'disposal_type' => 'scrap',
                'proceeds_minor' => 0,
            ]));

        $response->assertRedirect();

        $disposal = FixedAssetDisposal::where('fixed_asset_id', $this->asset->id)->firstOrFail();

        $this->assertEquals('posted', $disposal->status);
        $this->assertEquals('scrap', $disposal->disposal_type);
        $this->assertEquals(0, $disposal->proceeds_minor);
        $this->assertEquals(1100000, $disposal->net_book_value_minor);
        $this->assertEquals(1100000, $disposal->loss_minor);
        $this->assertEquals(0, $disposal->gain_minor);

        $this->asset->refresh();
        $this->assertEquals('disposed', $this->asset->status);

        /** @var JournalEntry $journal */
        $journal = JournalEntry::where('id', $disposal->journal_entry_id)->firstOrFail();
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(1200000, $journal->lines()->sum('debit_minor'));
        $this->assertEquals(1200000, $journal->lines()->sum('credit_minor'));
    }

    public function test_sale_disposal_posts_gain_correctly_with_integer_minor_units(): void
    {
        // Post 1 period depreciation: accum dep = 100,000, NBV = 1,100,000
        /** @var FixedAssetDepreciationPostingService $depPostingService */
        $depPostingService = app(FixedAssetDepreciationPostingService::class);
        $depPostingService->postDepreciationRun($this->period->id, $this->authorizedUser->id);

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/disposals", $this->disposalConfirmation([
                'disposal_date' => $this->period->end_date,
                'disposal_type' => 'sale',
                'proceeds_minor' => 1250000,
            ]));

        $response->assertRedirect();

        $disposal = FixedAssetDisposal::where('fixed_asset_id', $this->asset->id)->firstOrFail();

        $this->assertEquals('posted', $disposal->status);
        $this->assertEquals(1250000, $disposal->proceeds_minor);
        $this->assertEquals(1100000, $disposal->net_book_value_minor);
        $this->assertEquals(150000, $disposal->gain_minor);
        $this->assertEquals(0, $disposal->loss_minor);

        /** @var JournalEntry $journal */
        $journal = JournalEntry::where('id', $disposal->journal_entry_id)->firstOrFail();
        $this->assertEquals(1350000, $journal->lines()->sum('debit_minor'));
        $this->assertEquals(1350000, $journal->lines()->sum('credit_minor'));
    }

    public function test_sale_disposal_posts_loss_correctly_with_integer_minor_units(): void
    {
        // Post 1 period depreciation: accum dep = 100,000, NBV = 1,100,000
        /** @var FixedAssetDepreciationPostingService $depPostingService */
        $depPostingService = app(FixedAssetDepreciationPostingService::class);
        $depPostingService->postDepreciationRun($this->period->id, $this->authorizedUser->id);

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/disposals", $this->disposalConfirmation([
                'disposal_date' => $this->period->end_date,
                'disposal_type' => 'sale',
                'proceeds_minor' => 900000,
            ]));

        $response->assertRedirect();

        $disposal = FixedAssetDisposal::where('fixed_asset_id', $this->asset->id)->firstOrFail();

        $this->assertEquals('posted', $disposal->status);
        $this->assertEquals(900000, $disposal->proceeds_minor);
        $this->assertEquals(1100000, $disposal->net_book_value_minor);
        $this->assertEquals(0, $disposal->gain_minor);
        $this->assertEquals(200000, $disposal->loss_minor);

        /** @var JournalEntry $journal */
        $journal = JournalEntry::where('id', $disposal->journal_entry_id)->firstOrFail();
        $this->assertEquals(1200000, $journal->lines()->sum('debit_minor'));
        $this->assertEquals(1200000, $journal->lines()->sum('credit_minor'));
    }

    public function test_fully_depreciated_disposal_handles_zero_nbv(): void
    {
        // Set opening accum dep = 1200000 (fully depreciated)
        $this->asset->update(['opening_accumulated_depreciation_minor' => 1200000]);

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/disposals", $this->disposalConfirmation([
                'disposal_date' => $this->period->end_date,
                'disposal_type' => 'scrap',
                'proceeds_minor' => 0,
            ]));

        $response->assertRedirect();

        $disposal = FixedAssetDisposal::where('fixed_asset_id', $this->asset->id)->firstOrFail();

        $this->assertEquals(0, $disposal->net_book_value_minor);
        $this->assertEquals(0, $disposal->loss_minor);
        $this->assertEquals(0, $disposal->gain_minor);

        /** @var JournalEntry $journal */
        $journal = JournalEntry::where('id', $disposal->journal_entry_id)->firstOrFail();
        $this->assertEquals(1200000, $journal->lines()->sum('debit_minor'));
        $this->assertEquals(1200000, $journal->lines()->sum('credit_minor'));
    }

    public function test_closed_period_blocks_disposal(): void
    {
        $this->period->update(['status' => 'closed']);

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/disposals", $this->disposalConfirmation([
                'disposal_date' => $this->period->end_date,
                'disposal_type' => 'scrap',
                'proceeds_minor' => 0,
            ]));

        $response->assertSessionHasErrors();
        $this->assertEquals(0, FixedAssetDisposal::count());
    }

    public function test_missing_mappings_block_disposal(): void
    {
        AccountingAccountMapping::where('key', 'fixed_asset_disposal_loss')->delete();

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/disposals", $this->disposalConfirmation([
                'disposal_date' => $this->period->end_date,
                'disposal_type' => 'scrap',
                'proceeds_minor' => 0,
            ]));

        $response->assertSessionHasErrors();
        $this->assertEquals(0, FixedAssetDisposal::count());
    }

    public function test_cannot_dispose_twice(): void
    {
        /** @var FixedAssetDisposalPostingService $disposalService */
        $disposalService = app(FixedAssetDisposalPostingService::class);
        $disposalService->postDisposal($this->asset->id, $this->period->end_date, 'scrap', 0, $this->authorizedUser->id);

        $this->asset->refresh();
        $this->assertEquals('disposed', $this->asset->status);

        $this->expectException(ValidationException::class);
        $disposalService->postDisposal(
            fixedAssetId: $this->asset->id,
            disposalDate: $this->period->end_date,
            disposalType: 'scrap',
            proceedsMinor: 0,
            userId: $this->authorizedUser->id,
            idempotencyKey: 'fixed_asset_disposal_second_attempt:'.$this->asset->id
        );
    }

    public function test_cannot_depreciate_after_disposal_date(): void
    {
        /** @var FixedAssetDisposalPostingService $disposalService */
        $disposalService = app(FixedAssetDisposalPostingService::class);
        $disposalService->postDisposal($this->asset->id, $this->period->end_date, 'scrap', 0, $this->authorizedUser->id);

        $this->asset->refresh();
        $this->assertEquals('disposed', $this->asset->status);

        /** @var FixedAssetDepreciationPostingService $depPostingService */
        $depPostingService = app(FixedAssetDepreciationPostingService::class);

        $this->expectException(ValidationException::class);
        $depPostingService->postDepreciationRun($this->period->id, $this->authorizedUser->id);
    }

    public function test_cannot_backdate_disposal_before_posted_depreciation(): void
    {
        /** @var FixedAssetDepreciationPostingService $depPostingService */
        $depPostingService = app(FixedAssetDepreciationPostingService::class);
        $depPostingService->postDepreciationRun($this->period->id, $this->authorizedUser->id);

        /** @var FixedAssetDisposalPostingService $disposalService */
        $disposalService = app(FixedAssetDisposalPostingService::class);

        $this->expectException(ValidationException::class);

        $disposalService->postDisposal(
            fixedAssetId: $this->asset->id,
            disposalDate: $this->period->start_date,
            disposalType: 'scrap',
            proceedsMinor: 0,
            userId: $this->authorizedUser->id
        );
    }

    public function test_reversal_creates_reversing_journal_and_restores_asset_status(): void
    {
        /** @var FixedAssetDisposalPostingService $disposalService */
        $disposalService = app(FixedAssetDisposalPostingService::class);
        $disposal = $disposalService->postDisposal($this->asset->id, $this->period->end_date, 'scrap', 0, $this->authorizedUser->id);

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets-disposals/{$disposal->id}/reverse", $this->reverseDisposalConfirmation());

        $response->assertRedirect();

        $disposal->refresh();
        $this->assertEquals('reversed', $disposal->status);

        $this->asset->refresh();
        $this->assertEquals('active', $this->asset->status);

        $reversingJournal = JournalEntry::where('reverses_entry_id', $disposal->journal_entry_id)->first();
        $this->assertNotNull($reversingJournal);
    }

    public function test_reversed_disposal_can_be_corrected_with_a_new_disposal(): void
    {
        /** @var FixedAssetDisposalPostingService $disposalService */
        $disposalService = app(FixedAssetDisposalPostingService::class);
        $firstDisposal = $disposalService->postDisposal($this->asset->id, $this->period->end_date, 'scrap', 0, $this->authorizedUser->id);

        $disposalService->reverseDisposal($firstDisposal->id, $this->authorizedUser->id);

        $secondDisposal = $disposalService->postDisposal($this->asset->id, $this->period->end_date, 'scrap', 0, $this->authorizedUser->id);

        $this->assertNotEquals($firstDisposal->id, $secondDisposal->id);
        $this->assertEquals(1, FixedAssetDisposal::where('fixed_asset_id', $this->asset->id)->where('status', 'posted')->count());
        $this->assertEquals(1, FixedAssetDisposal::where('fixed_asset_id', $this->asset->id)->where('status', 'reversed')->count());

        $this->asset->refresh();
        $this->assertEquals('disposed', $this->asset->status);
    }

    public function test_database_enforces_disposal_financial_immutability(): void
    {
        /** @var FixedAssetDisposalPostingService $disposalService */
        $disposalService = app(FixedAssetDisposalPostingService::class);
        $disposal = $disposalService->postDisposal($this->asset->id, $this->period->end_date, 'scrap', 0, $this->authorizedUser->id);

        $this->expectException(QueryException::class);

        DB::table('fixed_asset_disposal')
            ->where('id', $disposal->id)
            ->update(['loss_minor' => 1]);
    }

    public function test_database_blocks_disposal_delete(): void
    {
        /** @var FixedAssetDisposalPostingService $disposalService */
        $disposalService = app(FixedAssetDisposalPostingService::class);
        $disposal = $disposalService->postDisposal($this->asset->id, $this->period->end_date, 'scrap', 0, $this->authorizedUser->id);

        $this->expectException(QueryException::class);

        DB::table('fixed_asset_disposal')
            ->where('id', $disposal->id)
            ->delete();
    }

    public function test_unauthorized_users_get_403(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/disposals", [
                'disposal_date' => $this->period->end_date,
                'disposal_type' => 'scrap',
                'proceeds_minor' => 0,
            ]);

        $response->assertStatus(403);
    }

    public function test_schema_has_no_prohibited_scope_columns(): void
    {
        foreach (['company_id', 'branch_id', 'tenant_id', 'custodian_id', 'employee_id', 'warehouse_id', 'location_id'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('fixed_asset_disposal', $column),
                "fixed_asset_disposal must not contain unsupported scope column [{$column}]."
            );
        }
    }
}
