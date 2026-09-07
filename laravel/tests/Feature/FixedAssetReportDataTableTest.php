<?php

namespace Tests\Feature;

use App\Models\FinancialPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciationRun;
use App\Models\FixedAssetDepreciationSchedule;
use App\Models\FixedAssetDisposal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FixedAssetReportDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $reportsOnlyUser;

    private FixedAssetCategory $category;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo(['reports.view', 'view_financials']);

        $this->reportsOnlyUser = User::factory()->create();
        $this->reportsOnlyUser->givePermissionTo('reports.view');

        $this->category = FixedAssetCategory::create([
            'code' => 'DT-ASSETS',
            'name' => ['en' => 'Data table assets', 'ar' => 'Data table assets'],
            'useful_life_months' => 12,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        $this->period = FinancialPeriod::query()->orderBy('start_date')->firstOrFail();
    }

    public function test_report_pages_do_not_embed_unbounded_row_sets(): void
    {
        foreach ([
            '/reports/fixed-asset-register' => ['Reports/FixedAssetRegisterReport', 'assets'],
            '/reports/fixed-asset-net-book-values' => ['Reports/FixedAssetNetBookValueReport', 'assets'],
            '/reports/fixed-asset-depreciation' => ['Reports/FixedAssetDepreciationReport', 'schedules'],
            '/reports/fixed-asset-depreciation-runs' => ['Reports/FixedAssetDepreciationRunReport', 'runs'],
            '/reports/fixed-asset-disposals' => ['Reports/FixedAssetDisposalReport', 'disposals'],
        ] as $uri => [$component, $rowsProp]) {
            $this->actingAs($this->viewer)
                ->get($uri)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where($rowsProp, []));
        }
    }

    public function test_data_feeds_require_report_and_financial_permissions(): void
    {
        foreach ($this->feedColumns() as $uri => $columns) {
            $query = $this->dataTableQuery($columns);

            $this->actingAs($this->reportsOnlyUser)
                ->getJson($uri.'?'.http_build_query($query))
                ->assertForbidden();

            $this->actingAs($this->viewer)
                ->getJson($uri.'?'.http_build_query($query))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_asset_feeds_search_paginate_and_calculate_values_on_the_server(): void
    {
        $assets = collect(range(1, 11))->map(function (int $index): FixedAsset {
            return $this->createAsset(sprintf('FA-DT-%03d', $index), $index === 11 ? 'Needle asset' : "Asset $index");
        });

        $needle = $assets->last();
        FixedAssetDepreciationSchedule::create([
            'fixed_asset_id' => $needle->id,
            'period_number' => 1,
            'financial_period_id' => $this->period->id,
            'period_start_date' => $this->period->start_date,
            'period_end_date' => $this->period->end_date,
            'depreciation_minor' => 100000,
            'accumulated_depreciation_minor' => 200000,
            'net_book_value_minor' => 1000000,
            'status' => 'posted',
        ]);

        $columns = $this->feedColumns()['/reports/fixed-asset-register/data'];
        $page = $this->actingAs($this->viewer)->getJson('/reports/fixed-asset-register/data?'.http_build_query(
            $this->dataTableQuery($columns, start: 10, search: 'FA-DT-')
        ));

        $page->assertOk()
            ->assertJsonPath('recordsFiltered', 11)
            ->assertJsonCount(1, 'data');

        foreach (['/reports/fixed-asset-register/data', '/reports/fixed-asset-net-book-values/data'] as $uri) {
            $response = $this->actingAs($this->viewer)->getJson($uri.'?'.http_build_query(
                $this->dataTableQuery($this->feedColumns()[$uri], search: 'FA-DT-011')
            ));

            $response->assertOk()
                ->assertJsonPath('recordsFiltered', 1)
                ->assertJsonPath('data.0.id', $needle->id)
                ->assertJsonPath('data.0.posted_accumulated_depreciation_minor', 100000)
                ->assertJsonPath('data.0.total_accumulated_depreciation_minor', 200000)
                ->assertJsonPath('data.0.net_book_value_minor', 1000000);
        }
    }

    public function test_schedule_run_and_disposal_feeds_search_their_large_history_sets(): void
    {
        $asset = $this->createAsset('FA-DT-HISTORY-NEEDLE', 'History needle');
        $otherAsset = $this->createAsset('FA-DT-HISTORY-OTHER', 'History other');

        $needleRun = $this->createRun('FADR-DT-NEEDLE');
        $otherRun = $this->createRun('FADR-DT-OTHER');

        $this->createSchedule($asset, $needleRun, 1);
        $this->createSchedule($otherAsset, $otherRun, 2);

        $needleDisposal = $this->createDisposal($asset, 'FAD-DT-NEEDLE');
        $this->createDisposal($otherAsset, 'FAD-DT-OTHER');

        foreach ([
            '/reports/fixed-asset-depreciation/data' => ['FA-DT-HISTORY-NEEDLE', 'data.0.asset.id', $asset->id],
            '/reports/fixed-asset-depreciation-runs/data' => ['FADR-DT-NEEDLE', 'data.0.id', $needleRun->id],
            '/reports/fixed-asset-disposals/data' => ['FAD-DT-NEEDLE', 'data.0.id', $needleDisposal->id],
        ] as $uri => [$search, $jsonPath, $expected]) {
            $this->actingAs($this->viewer)
                ->getJson($uri.'?'.http_build_query(
                    $this->dataTableQuery($this->feedColumns()[$uri], search: $search)
                ))
                ->assertOk()
                ->assertJsonPath('recordsFiltered', 1)
                ->assertJsonPath($jsonPath, $expected)
                ->assertJsonCount(1, 'data');
        }
    }

    /** @return array<string, list<array{data: string, name: string, searchable?: bool, orderable?: bool}>> */
    private function feedColumns(): array
    {
        return [
            '/reports/fixed-asset-register/data' => [
                ['data' => 'asset_number', 'name' => 'asset_number'],
                ['data' => 'name', 'name' => 'name', 'orderable' => false],
                ['data' => 'category', 'name' => 'category', 'orderable' => false],
                ['data' => 'cost_minor', 'name' => 'cost_minor', 'searchable' => false],
                ['data' => 'total_accumulated_depreciation_minor', 'name' => 'total_accumulated_depreciation_minor', 'searchable' => false],
                ['data' => 'net_book_value_minor', 'name' => 'net_book_value_minor', 'searchable' => false],
                ['data' => 'status', 'name' => 'status'],
            ],
            '/reports/fixed-asset-net-book-values/data' => [
                ['data' => 'asset_number', 'name' => 'asset_number'],
                ['data' => 'name', 'name' => 'name', 'orderable' => false],
                ['data' => 'cost_minor', 'name' => 'cost_minor', 'searchable' => false],
                ['data' => 'opening_accumulated_depreciation_minor', 'name' => 'opening_accumulated_depreciation_minor', 'searchable' => false],
                ['data' => 'posted_accumulated_depreciation_minor', 'name' => 'posted_accumulated_depreciation_minor', 'searchable' => false],
                ['data' => 'total_accumulated_depreciation_minor', 'name' => 'total_accumulated_depreciation_minor', 'searchable' => false],
                ['data' => 'net_book_value_minor', 'name' => 'net_book_value_minor', 'searchable' => false],
                ['data' => 'status', 'name' => 'status'],
            ],
            '/reports/fixed-asset-depreciation/data' => [
                ['data' => 'asset', 'name' => 'asset', 'orderable' => false],
                ['data' => 'period_number', 'name' => 'period_number', 'searchable' => false],
                ['data' => 'period_start_date', 'name' => 'period_start_date'],
                ['data' => 'period_end_date', 'name' => 'period_end_date'],
                ['data' => 'depreciation_minor', 'name' => 'depreciation_minor', 'searchable' => false],
                ['data' => 'accumulated_depreciation_minor', 'name' => 'accumulated_depreciation_minor', 'searchable' => false],
                ['data' => 'net_book_value_minor', 'name' => 'net_book_value_minor', 'searchable' => false],
                ['data' => 'status', 'name' => 'status'],
            ],
            '/reports/fixed-asset-depreciation-runs/data' => [
                ['data' => 'number', 'name' => 'number'],
                ['data' => 'run_date', 'name' => 'run_date'],
                ['data' => 'financial_period', 'name' => 'financial_period', 'orderable' => false],
                ['data' => 'asset_count', 'name' => 'asset_count', 'searchable' => false],
                ['data' => 'total_depreciation_minor', 'name' => 'total_depreciation_minor', 'searchable' => false],
                ['data' => 'journal_number', 'name' => 'journal_number', 'orderable' => false],
                ['data' => 'status', 'name' => 'status'],
            ],
            '/reports/fixed-asset-disposals/data' => [
                ['data' => 'number', 'name' => 'number'],
                ['data' => 'asset', 'name' => 'asset', 'orderable' => false],
                ['data' => 'disposal_date', 'name' => 'disposal_date'],
                ['data' => 'disposal_type', 'name' => 'disposal_type'],
                ['data' => 'proceeds_minor', 'name' => 'proceeds_minor', 'searchable' => false],
                ['data' => 'net_book_value_minor', 'name' => 'net_book_value_minor', 'searchable' => false],
                ['data' => 'gain_loss_minor', 'name' => 'gain_loss_minor', 'searchable' => false],
                ['data' => 'status', 'name' => 'status'],
            ],
        ];
    }

    /**
     * @param  list<array{data: string, name: string, searchable?: bool, orderable?: bool}>  $columns
     * @return array<string, mixed>
     */
    private function dataTableQuery(array $columns, int $start = 0, string $search = ''): array
    {
        return [
            'draw' => 1,
            'start' => $start,
            'length' => 10,
            'columns' => array_map(fn (array $column): array => [
                'data' => $column['data'],
                'name' => $column['name'],
                'searchable' => $column['searchable'] ?? true,
                'orderable' => $column['orderable'] ?? true,
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }

    private function createAsset(string $number, string $name): FixedAsset
    {
        return FixedAsset::create([
            'asset_number' => $number,
            'name' => ['en' => $name, 'ar' => $name],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 1200000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 100000,
            'status' => 'active',
        ]);
    }

    private function createRun(string $number): FixedAssetDepreciationRun
    {
        return FixedAssetDepreciationRun::create([
            'number' => $number,
            'financial_period_id' => $this->period->id,
            'run_date' => $this->period->end_date,
            'total_depreciation_minor' => 100000,
            'asset_count' => 1,
            'status' => 'posted',
        ]);
    }

    private function createSchedule(FixedAsset $asset, FixedAssetDepreciationRun $run, int $periodNumber): FixedAssetDepreciationSchedule
    {
        return FixedAssetDepreciationSchedule::create([
            'fixed_asset_id' => $asset->id,
            'period_number' => $periodNumber,
            'financial_period_id' => $this->period->id,
            'period_start_date' => $this->period->start_date,
            'period_end_date' => $this->period->end_date,
            'depreciation_minor' => 100000,
            'accumulated_depreciation_minor' => 200000,
            'net_book_value_minor' => 1000000,
            'status' => 'posted',
            'depreciation_run_id' => $run->id,
        ]);
    }

    private function createDisposal(FixedAsset $asset, string $number): FixedAssetDisposal
    {
        return FixedAssetDisposal::create([
            'number' => $number,
            'fixed_asset_id' => $asset->id,
            'disposal_date' => $this->period->end_date,
            'financial_period_id' => $this->period->id,
            'disposal_type' => 'sale',
            'proceeds_minor' => 1100000,
            'net_book_value_minor' => 1000000,
            'gain_minor' => 100000,
            'loss_minor' => 0,
            'status' => 'posted',
        ]);
    }
}
