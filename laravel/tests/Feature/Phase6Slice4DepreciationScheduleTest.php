<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6Slice4DepreciationScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    private FixedAssetCategory $category;

    private FixedAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo([
            'fixedAssets.view',
            'fixedAssets.create',
            'fixedAssets.edit',
            'view_financials',
        ]);

        $this->unauthorizedUser = User::factory()->create();

        $this->category = FixedAssetCategory::create([
            'id' => (string) Str::uuid(),
            'code' => 'EQUIPMENT',
            'name' => ['en' => 'Office Equipment', 'ar' => 'معدات مكتبية'],
            'useful_life_months' => 36,
            'salvage_value_minor' => 10000,
            'is_active' => true,
        ]);

        $this->asset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-00200',
            'name' => ['en' => 'Server Rack', 'ar' => 'خادم شبكات'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-10',
            'in_service_date' => '2026-01-15',
            'cost_minor' => 5000000,
            'salvage_value_minor' => 10000,
            'useful_life_months' => 36,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
        ]);
    }

    public function test_straight_line_integer_schedule_totals_exactly_equal_depreciable_base(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        $response->assertRedirect();

        $schedules = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->orderBy('period_number')
            ->get();

        $this->assertCount(36, $schedules);

        $expectedBase = 5000000 - 10000; // 4,990,000
        $sumDepreciation = $schedules->sum('depreciation_minor');

        $this->assertEquals($expectedBase, $sumDepreciation);
    }

    public function test_planned_depreciation_schedule_blocks_its_financial_period_close(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule")
            ->assertRedirect();

        $schedule = FixedAssetDepreciationSchedule::query()
            ->with('financialPeriod')
            ->where('fixed_asset_id', $this->asset->id)
            ->where('status', 'planned')
            ->orderBy('period_number')
            ->firstOrFail();

        $readiness = app(PeriodService::class)->checkCloseReadiness($schedule->financialPeriod);

        $this->assertFalse($readiness['can_close']);
        $this->assertTrue(collect($readiness['blockers'])->contains(
            fn (array $blocker): bool => $blocker['entity_type'] === 'fixed_asset_depreciation_schedule'
                && $blocker['id'] === $schedule->id
                && $blocker['reason_code'] === 'unposted_fixed_asset_depreciation'
        ));
    }

    public function test_remainder_cents_are_allocated_deterministically(): void
    {
        // 4,990,000 / 36 = 138611 base, remainder = 4
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        $schedules = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->orderBy('period_number')
            ->get();

        for ($i = 0; $i < 4; $i++) {
            $this->assertEquals(138612, $schedules[$i]->depreciation_minor, 'Period '.($i + 1).' should get remainder cent.');
        }

        for ($i = 4; $i < 36; $i++) {
            $this->assertEquals(138611, $schedules[$i]->depreciation_minor, 'Period '.($i + 1).' should get base monthly minor.');
        }
    }

    public function test_schedule_generation_starts_in_month_after_in_service_date(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule")
            ->assertRedirect();

        /** @var FixedAssetDepreciationSchedule $firstSchedule */
        $firstSchedule = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('period_number', 1)
            ->firstOrFail();

        $this->assertEquals('2026-02-01', $firstSchedule->period_start_date->format('Y-m-d'));
        $this->assertEquals('2026-02-28', $firstSchedule->period_end_date->format('Y-m-d'));
    }

    public function test_opening_accumulated_depreciation_reduces_depreciable_base(): void
    {
        $openingAsset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-00201',
            'name' => ['en' => 'Used Vehicle', 'ar' => 'مركبة مستعملة'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-10',
            'in_service_date' => '2026-01-15',
            'cost_minor' => 1000000,
            'salvage_value_minor' => 50000,
            'useful_life_months' => 20,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 150000,
            'status' => 'active',
        ]);

        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$openingAsset->id}/generate-schedule");

        $schedules = FixedAssetDepreciationSchedule::where('fixed_asset_id', $openingAsset->id)
            ->orderBy('period_number')
            ->get();

        $expectedBase = 1000000 - 50000 - 150000; // 800,000
        $sumDepreciation = $schedules->sum('depreciation_minor');

        $this->assertEquals($expectedBase, $sumDepreciation);
    }

    public function test_salvage_value_floor_is_respected_at_last_period(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        /** @var FixedAssetDepreciationSchedule $lastSchedule */
        $lastSchedule = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('period_number', 36)
            ->firstOrFail();

        $this->assertEquals(10000, $lastSchedule->net_book_value_minor);
        $this->assertEquals(4990000, $lastSchedule->accumulated_depreciation_minor);
    }

    public function test_schedule_generation_creates_zero_gl_journal_or_ledger_entries(): void
    {
        $initialJournals = JournalEntry::count();
        $initialLedgers = LedgerEntry::count();

        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        $this->assertEquals($initialJournals, JournalEntry::count());
        $this->assertEquals($initialLedgers, LedgerEntry::count());
    }

    public function test_schedule_generation_requires_active_fixed_asset(): void
    {
        $draftAsset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-DRAFT-SCHED',
            'name' => ['en' => 'Draft Asset', 'ar' => 'أصل مسودة'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-10',
            'in_service_date' => '2026-01-15',
            'cost_minor' => 500000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$draftAsset->id}/generate-schedule");

        $response->assertSessionHasErrors(['asset']);
        $this->assertEquals(0, FixedAssetDepreciationSchedule::where('fixed_asset_id', $draftAsset->id)->count());
    }

    public function test_repeated_schedule_generation_is_idempotent(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        $countFirst = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)->count();

        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        $countSecond = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)->count();

        $this->assertEquals(36, $countFirst);
        $this->assertEquals(36, $countSecond);
    }

    public function test_posted_schedule_rows_are_not_mutated_on_regeneration(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        /** @var FixedAssetDepreciationSchedule $firstRow */
        $firstRow = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('period_number', 1)
            ->firstOrFail();

        $firstRow->update([
            'status' => 'posted',
            'depreciation_minor' => 999999,
        ]);

        // Regenerate schedule
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        $firstRow->refresh();
        $this->assertEquals('posted', $firstRow->status);
        $this->assertEquals(999999, $firstRow->depreciation_minor);
    }

    public function test_database_blocks_financial_mutation_of_posted_schedule_rows(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        /** @var FixedAssetDepreciationSchedule $firstRow */
        $firstRow = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('period_number', 1)
            ->firstOrFail();

        $firstRow->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->authorizedUser->id]);

        $this->expectException(QueryException::class);

        DB::table('fixed_asset_depreciation_schedule')
            ->where('id', $firstRow->id)
            ->update(['depreciation_minor' => $firstRow->depreciation_minor + 1]);
    }

    public function test_database_blocks_delete_of_posted_schedule_rows(): void
    {
        $this->actingAs($this->authorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule");

        /** @var FixedAssetDepreciationSchedule $firstRow */
        $firstRow = FixedAssetDepreciationSchedule::where('fixed_asset_id', $this->asset->id)
            ->where('period_number', 1)
            ->firstOrFail();

        $firstRow->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $this->authorizedUser->id]);

        $this->expectException(QueryException::class);

        DB::table('fixed_asset_depreciation_schedule')
            ->where('id', $firstRow->id)
            ->delete();
    }

    public function test_permission_gates_block_unauthorized_schedule_generation(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->post("/fixed-assets/{$this->asset->id}/generate-schedule")
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
                Schema::hasColumn('fixed_asset_depreciation_schedule', $col),
                "fixed_asset_depreciation_schedule must NOT contain column [{$col}]."
            );
        }
    }
}
