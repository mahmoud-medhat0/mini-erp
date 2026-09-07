<?php

namespace Tests\Feature;

use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FixedAssetDepreciationRunDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FixedAssetDepreciationRun $run;

    private string $periodId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['fixedAssets.view', 'view_financials']);

        $fiscalYearId = (string) Str::uuid();
        $periodId = (string) Str::uuid();
        $this->periodId = $periodId;
        DB::table('fiscal_year')->insert([
            'id' => $fiscalYearId,
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        DB::table('financial_period')->insert([
            'id' => $periodId,
            'fiscal_year_id' => $fiscalYearId,
            'month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        $this->run = FixedAssetDepreciationRun::query()->create([
            'number' => 'FADR-DT-001',
            'financial_period_id' => $periodId,
            'run_date' => '2026-09-30',
            'total_depreciation_minor' => 125000,
            'asset_count' => 4,
            'status' => 'posted',
            'posted_by' => $this->user->id,
        ]);

        $category = FixedAssetCategory::query()->create([
            'code' => 'FADR-DT-CAT',
            'name' => ['en' => 'DataTable Category', 'ar' => 'فئة جدول البيانات'],
            'useful_life_months' => 24,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);
        $postedAsset = $this->asset($category, 'FA-DT-POSTED');
        $plannedAsset = $this->asset($category, 'FA-DT-PLANNED');
        FixedAssetDepreciationSchedule::query()->create([
            'fixed_asset_id' => $postedAsset->id,
            'period_number' => 1,
            'financial_period_id' => $periodId,
            'period_start_date' => '2026-09-01',
            'period_end_date' => '2026-09-30',
            'depreciation_minor' => 5000,
            'accumulated_depreciation_minor' => 5000,
            'net_book_value_minor' => 115000,
            'status' => 'posted',
            'depreciation_run_id' => $this->run->id,
        ]);
        FixedAssetDepreciationSchedule::query()->create([
            'fixed_asset_id' => $plannedAsset->id,
            'period_number' => 1,
            'financial_period_id' => $periodId,
            'period_start_date' => '2026-09-01',
            'period_end_date' => '2026-09-30',
            'depreciation_minor' => 6000,
            'accumulated_depreciation_minor' => 6000,
            'net_book_value_minor' => 114000,
            'status' => 'planned',
        ]);
    }

    public function test_index_does_not_serialize_the_run_history(): void
    {
        $this->actingAs($this->user)
            ->get('/fixed-assets-depreciation-runs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FixedAssets/DepreciationRuns/Index')
                ->where('runs', []));
    }

    public function test_run_history_is_searched_and_paginated_server_side(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/fixed-assets-depreciation-runs/data?'.http_build_query($this->gridQuery()),
        );

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.number', 'FADR-DT-001')
            ->assertJsonPath('data.0.total_depreciation_minor', 125000)
            ->assertJsonPath('data.0.financial_period.start_date', '2026-09-01');
    }

    public function test_run_history_requires_both_sensitive_view_permissions(): void
    {
        $stranger = User::factory()->create();
        $stranger->givePermissionTo('fixedAssets.view');

        $this->actingAs($stranger)
            ->getJson('/fixed-assets-depreciation-runs/data?'.http_build_query($this->gridQuery()))
            ->assertForbidden();
    }

    public function test_run_and_preview_schedule_rows_are_loaded_from_bounded_feeds(): void
    {
        $this->actingAs($this->user)
            ->getJson('/fixed-assets-depreciation-runs/'.$this->run->id.'/data?'.http_build_query($this->scheduleGridQuery('FA-DT-POSTED')))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.asset_number', 'FA-DT-POSTED')
            ->assertJsonPath('data.0.depreciation_minor', 5000);

        $this->actingAs($this->user)
            ->get('/fixed-assets-depreciation-runs/preview/'.$this->periodId)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('assetCount', 1)
                ->where('totalDepreciationMinor', 6000)
                ->has('schedules', 1));

        $this->actingAs($this->user)
            ->getJson('/fixed-assets-depreciation-runs/preview/'.$this->periodId.'/data?'.http_build_query($this->scheduleGridQuery('FA-DT-PLANNED')))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.asset_number', 'FA-DT-PLANNED')
            ->assertJsonPath('data.0.depreciation_minor', 6000);
    }

    private function asset(FixedAssetCategory $category, string $number): FixedAsset
    {
        return FixedAsset::query()->create([
            'asset_number' => $number,
            'name' => ['en' => $number, 'ar' => $number],
            'fixed_asset_category_id' => $category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 120000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 24,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
        ]);
    }

    /** @return array<string, mixed> */
    private function scheduleGridQuery(string $search): array
    {
        $columns = [
            ['asset_number', true, true],
            ['asset_name', true, true],
            ['category_name', true, true],
            ['period_number', false, true],
            ['depreciation_minor', false, true],
            ['accumulated_depreciation_minor', false, true],
            ['net_book_value_minor', false, true],
        ];

        return [
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => array_map(fn (array $column): array => [
                'data' => $column[0],
                'name' => $column[0],
                'searchable' => $column[1] ? 'true' : 'false',
                'orderable' => $column[2] ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => '0', 'dir' => 'asc']],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }

    /** @return array<string, mixed> */
    private function gridQuery(): array
    {
        $columns = [
            ['number', true, true],
            ['run_date', false, true],
            ['financial_period', false, false],
            ['asset_count', false, true],
            ['total_depreciation_minor', false, true],
            ['status', true, true],
            ['actions', false, false],
        ];

        return [
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => array_map(fn (array $column): array => [
                'data' => $column[0],
                'name' => $column[0],
                'searchable' => $column[1] ? 'true' : 'false',
                'orderable' => $column[2] ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => '1', 'dir' => 'desc']],
            'search' => ['value' => 'FADR-DT', 'regex' => 'false'],
        ];
    }
}
