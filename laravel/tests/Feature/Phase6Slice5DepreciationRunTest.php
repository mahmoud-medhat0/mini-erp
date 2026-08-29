<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\FixedAssets\FixedAssetDepreciationEngineService;
use App\Models\AccountingAccountMapping;
use App\Models\FinancialPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6Slice5DepreciationRunTest extends TestCase
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
    private function depreciationRunConfirmation(array $payload = []): array
    {
        return array_merge($payload, [
            'confirm_action' => 'STORE_FIXED_ASSET_DEPRECIATION_RUN',
            'reason' => 'Automated fixed asset depreciation run test approval.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function reverseDepreciationRunConfirmation(array $payload = []): array
    {
        return array_merge($payload, [
            'confirm_action' => 'REVERSE_FIXED_ASSET_DEPRECIATION_RUN',
            'reason' => 'Automated fixed asset depreciation reversal test approval.',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

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

        $this->category = FixedAssetCategory::create([
            'id' => (string) Str::uuid(),
            'code' => 'MACHINERY',
            'name' => ['en' => 'Industrial Machinery', 'ar' => 'آلات صناعية'],
            'useful_life_months' => 12,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        $this->asset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-00500',
            'name' => ['en' => 'CNC Milling Machine', 'ar' => 'ماكينة سي إن سي'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 1200000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
        ]);

        /** @var FixedAssetDepreciationEngineService $engine */
        $engine = app(FixedAssetDepreciationEngineService::class);
        $schedules = $engine->generateSchedule($this->asset->id);

        /** @var FixedAssetDepreciationSchedule $firstSchedule */
        $firstSchedule = $schedules->first();

        /** @var FinancialPeriod $period */
        $period = FinancialPeriod::query()->where('id', $firstSchedule->financial_period_id)->firstOrFail();
        $this->period = $period;
    }

    public function test_successful_depreciation_run_posts_balanced_journal(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $response->assertRedirect();

        /** @var FixedAssetDepreciationRun $run */
        $run = FixedAssetDepreciationRun::where('financial_period_id', $this->period->id)->firstOrFail();

        $this->assertEquals('posted', $run->status);
        $this->assertEquals(100000, $run->total_depreciation_minor);
        $this->assertEquals(1, $run->asset_count);

        /** @var JournalEntry $journal */
        $journal = JournalEntry::where('id', $run->journal_entry_id)->firstOrFail();

        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(100000, $journal->lines()->sum('debit_minor'));
        $this->assertEquals(100000, $journal->lines()->sum('credit_minor'));

        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        $mappingService = app(AccountingAccountMappingService::class);
        $expenseAccountId = $mappingService->getAccount('depreciation_expense')->id;
        $accumulatedAccountId = $mappingService->getAccount('accumulated_depreciation')->id;

        $expenseLine = $lines->firstWhere('account_id', $expenseAccountId);
        $accumulatedLine = $lines->firstWhere('account_id', $accumulatedAccountId);

        $this->assertNotNull($expenseLine);
        $this->assertNotNull($accumulatedLine);
        $this->assertEquals(100000, $expenseLine->debit_minor);
        $this->assertEquals(0, $expenseLine->credit_minor);
        $this->assertEquals(0, $accumulatedLine->debit_minor);
        $this->assertEquals(100000, $accumulatedLine->credit_minor);
    }

    public function test_schedule_rows_are_linked_to_depreciation_run_and_journal(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $run = FixedAssetDepreciationRun::where('financial_period_id', $this->period->id)->firstOrFail();

        /** @var FixedAssetDepreciationSchedule $schedule */
        $schedule = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('financial_period_id', $this->period->id)
            ->firstOrFail();

        $this->assertEquals('posted', $schedule->status);
        $this->assertEquals($run->id, $schedule->depreciation_run_id);
        $this->assertEquals($run->journal_entry_id, $schedule->journal_entry_id);
    }

    public function test_posted_schedule_run_link_is_database_immutable(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        /** @var FixedAssetDepreciationSchedule $schedule */
        $schedule = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('financial_period_id', $this->period->id)
            ->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('fixed_asset_depreciation_schedule')
            ->where('id', $schedule->id)
            ->update(['depreciation_run_id' => null]);
    }

    public function test_closed_period_blocks_depreciation_run_posting(): void
    {
        $this->period->update(['status' => 'closed']);

        $response = $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $response->assertSessionHasErrors(['financial_period_id']);

        $this->assertEquals(0, FixedAssetDepreciationRun::count());
    }

    public function test_idempotent_repeated_posting_does_not_duplicate_run_or_journal(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $runCountFirst = FixedAssetDepreciationRun::count();
        $journalCountFirst = JournalEntry::count();

        $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $this->assertEquals($runCountFirst, FixedAssetDepreciationRun::count());
        $this->assertEquals($journalCountFirst, JournalEntry::count());
    }

    public function test_missing_gl_mappings_block_depreciation_run_posting(): void
    {
        AccountingAccountMapping::query()
            ->where('key', 'depreciation_expense')
            ->delete();

        $response = $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $response->assertSessionHasErrors(['account_mapping']);

        $this->assertEquals(0, FixedAssetDepreciationRun::count());
        $this->assertEquals(0, JournalEntry::where('source_type', 'fixed_asset_depreciation_run')->count());
    }

    public function test_reversing_depreciation_run_creates_reversing_journal_and_marks_schedules_reversed(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $run = FixedAssetDepreciationRun::where('financial_period_id', $this->period->id)->firstOrFail();

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets-depreciation-runs/{$run->id}/reverse", $this->reverseDepreciationRunConfirmation());

        $response->assertRedirect();

        $run->refresh();
        $this->assertEquals('reversed', $run->status);

        /** @var FixedAssetDepreciationSchedule $schedule */
        $schedule = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('financial_period_id', $this->period->id)
            ->firstOrFail();

        $this->assertEquals('reversed', $schedule->status);
        $this->assertEquals($run->id, $schedule->depreciation_run_id);
        $this->assertEquals($run->journal_entry_id, $schedule->journal_entry_id);

        $reversingJournal = JournalEntry::where('reverses_entry_id', $run->journal_entry_id)->first();
        $this->assertNotNull($reversingJournal);
    }

    public function test_no_posting_for_disposed_assets(): void
    {
        $this->asset->update(['status' => 'disposed']);

        $response = $this->actingAs($this->authorizedUser)
            ->post('/fixed-assets-depreciation-runs', $this->depreciationRunConfirmation([
                'financial_period_id' => $this->period->id,
            ]));

        $response->assertSessionHasErrors(['financial_period_id']);
        $this->assertEquals(0, FixedAssetDepreciationRun::count());
    }

    public function test_unauthorized_users_get_403(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->post('/fixed-assets-depreciation-runs', [
                'financial_period_id' => $this->period->id,
            ])
            ->assertStatus(403);
    }

    public function test_schema_has_no_prohibited_columns(): void
    {
        $prohibited = [
            'company_id',
            'branch_id',
            'tenant_id',
            'custodian_id',
            'employee_id',
            'warehouse_id',
            'location_id',
        ];

        foreach ($prohibited as $col) {
            $this->assertFalse(
                Schema::hasColumn('fixed_asset_depreciation_run', $col),
                "fixed_asset_depreciation_run must NOT contain column [{$col}]."
            );
        }
    }
}
