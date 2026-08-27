<?php

namespace Tests\Feature;

use App\Models\AccountingAccountMapping;
use App\Models\FinancialPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6Slice3CapitalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $postUser;

    private User $unauthorizedUser;

    private FixedAssetCategory $category;

    private FixedAsset $draftAsset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->postUser = User::factory()->create();
        $this->postUser->givePermissionTo([
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
            'useful_life_months' => 120,
            'salvage_value_minor' => 10000,
            'is_active' => true,
        ]);

        $this->draftAsset = FixedAsset::create([
            'id' => (string) Str::uuid(),
            'asset_number' => 'FA-2026-00100',
            'name' => ['en' => 'CNC Lathe Machine', 'ar' => 'ماكينة مخرطة CNC'],
            'fixed_asset_category_id' => $this->category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-10',
            'in_service_date' => '2026-01-15',
            'cost_minor' => 5000000,
            'salvage_value_minor' => 10000,
            'useful_life_months' => 120,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'draft',
        ]);
    }

    public function test_opening_already_capitalized_creates_no_journal_or_ledger_entries(): void
    {
        $initialJournals = JournalEntry::count();
        $initialLedgers = LedgerEntry::count();

        $response = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'opening_already_capitalized',
            'capitalization_date' => '2026-01-15',
        ]);

        $response->assertRedirect();
        $this->draftAsset->refresh();

        $this->assertEquals('active', $this->draftAsset->status);
        $this->assertEquals('opening_already_capitalized', $this->draftAsset->capitalization_mode);
        $this->assertNull($this->draftAsset->journal_entry_id);

        $this->assertEquals($initialJournals, JournalEntry::count());
        $this->assertEquals($initialLedgers, LedgerEntry::count());
    }

    public function test_manual_capitalization_posts_balanced_journal_entry(): void
    {
        $response = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $response->assertRedirect();
        $this->draftAsset->refresh();

        $this->assertEquals('active', $this->draftAsset->status);
        $this->assertEquals('manual_capitalization', $this->draftAsset->capitalization_mode);
        $this->assertNotNull($this->draftAsset->journal_entry_id);

        /** @var JournalEntry $journal */
        $journal = JournalEntry::with('lines')->findOrFail($this->draftAsset->journal_entry_id);
        $this->assertEquals('fixed_asset_capitalization', $journal->source_type);
        $this->assertEquals('posted', $journal->status);
        $this->assertSame("fixed_asset.capitalization:{$this->draftAsset->asset_number}", $journal->description);
        $this->assertCount(2, $journal->lines);

        $costMapping = AccountingAccountMapping::where('key', 'fixed_asset_cost')->firstOrFail();
        $clearingMapping = AccountingAccountMapping::where('key', 'fixed_asset_clearing')->firstOrFail();

        $debitLine = $journal->lines->where('account_id', $costMapping->account_id)->first();
        $creditLine = $journal->lines->where('account_id', $clearingMapping->account_id)->first();

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertSame("fixed_asset.capitalization.cost:{$this->draftAsset->asset_number}", $debitLine->memo);
        $this->assertSame("fixed_asset.capitalization.clearing:{$this->draftAsset->asset_number}", $creditLine->memo);
        $this->assertEquals(5000000, $debitLine->debit_minor);
        $this->assertEquals(0, $debitLine->credit_minor);
        $this->assertEquals(0, $creditLine->debit_minor);
        $this->assertEquals(5000000, $creditLine->credit_minor);

        $this->assertEquals(2, LedgerEntry::where('journal_entry_id', $journal->id)->count());
    }

    public function test_manual_capitalization_is_idempotent(): void
    {
        $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $this->draftAsset->refresh();
        $firstJournalId = $this->draftAsset->journal_entry_id;

        // Re-post capitalization request
        $response = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $response->assertRedirect();
        $this->draftAsset->refresh();

        $this->assertEquals($firstJournalId, $this->draftAsset->journal_entry_id);
        $this->assertEquals(1, JournalEntry::where('source_type', 'fixed_asset_capitalization')->where('source_id', $this->draftAsset->id)->count());
    }

    public function test_capitalized_asset_cannot_be_recapitalized_with_a_different_mode(): void
    {
        $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'opening_already_capitalized',
            'capitalization_date' => '2026-01-15',
        ])->assertRedirect();

        $response = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors(['asset']);
        $this->draftAsset->refresh();

        $this->assertEquals('active', $this->draftAsset->status);
        $this->assertEquals('opening_already_capitalized', $this->draftAsset->capitalization_mode);
        $this->assertEquals(0, JournalEntry::where('source_type', 'fixed_asset_capitalization')->where('source_id', $this->draftAsset->id)->count());
    }

    public function test_closed_period_blocks_manual_capitalization(): void
    {
        // Close Jan 2026 period
        $period = FinancialPeriod::where('start_date', '<=', '2026-01-15')->where('end_date', '>=', '2026-01-15')->firstOrFail();
        $period->update(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors();
        $this->draftAsset->refresh();
        $this->assertEquals('draft', $this->draftAsset->status);
    }

    public function test_failed_closed_period_capitalization_can_be_retried_after_period_reopens(): void
    {
        $period = FinancialPeriod::where('start_date', '<=', '2026-01-15')->where('end_date', '>=', '2026-01-15')->firstOrFail();
        $period->update(['status' => 'closed', 'closed_at' => now()]);

        $firstResponse = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $firstResponse->assertSessionHasErrors(['capitalization_date']);
        $this->assertEquals(0, JournalEntry::where('source_type', 'fixed_asset_capitalization')->where('source_id', $this->draftAsset->id)->count());

        $period->update(['status' => 'open', 'closed_at' => null]);

        $retryResponse = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $retryResponse->assertRedirect();
        $this->draftAsset->refresh();

        $this->assertEquals('active', $this->draftAsset->status);
        $this->assertEquals(1, JournalEntry::where('source_type', 'fixed_asset_capitalization')->where('source_id', $this->draftAsset->id)->count());
    }

    public function test_non_draft_uncapitalized_asset_cannot_be_capitalized(): void
    {
        $this->draftAsset->update(['status' => 'active']);

        $response = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors(['asset']);
        $this->assertEquals(0, JournalEntry::where('source_type', 'fixed_asset_capitalization')->where('source_id', $this->draftAsset->id)->count());
    }

    public function test_active_assets_cannot_be_edited_or_updated_through_register_routes(): void
    {
        $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'opening_already_capitalized',
            'capitalization_date' => '2026-01-15',
        ])->assertRedirect();

        $this->actingAs($this->postUser)
            ->get("/fixed-assets/{$this->draftAsset->id}/edit")
            ->assertStatus(403);

        $this->actingAs($this->postUser)
            ->put("/fixed-assets/{$this->draftAsset->id}", [
                'cost_minor' => 6000000,
            ])
            ->assertSessionHasErrors(['asset']);

        $this->assertDatabaseHas('fixed_asset', [
            'id' => $this->draftAsset->id,
            'cost_minor' => 5000000,
            'status' => 'active',
        ]);
    }

    public function test_reversal_of_capitalization_resets_asset_to_draft(): void
    {
        $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
            'capitalization_mode' => 'manual_capitalization',
            'capitalization_date' => '2026-01-15',
        ]);

        $this->draftAsset->refresh();
        $this->assertEquals('active', $this->draftAsset->status);

        $reverseResp = $this->actingAs($this->postUser)->post("/fixed-assets/{$this->draftAsset->id}/reverse-capitalization");
        $reverseResp->assertRedirect();

        $this->draftAsset->refresh();
        $this->assertEquals('draft', $this->draftAsset->status);
        $this->assertNull($this->draftAsset->capitalization_mode);
        $this->assertNull($this->draftAsset->journal_entry_id);
    }

    public function test_permission_gates_block_unauthorized_capitalization_and_reversal(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->post("/fixed-assets/{$this->draftAsset->id}/capitalize", [
                'capitalization_mode' => 'manual_capitalization',
                'capitalization_date' => '2026-01-15',
            ])->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->post("/fixed-assets/{$this->draftAsset->id}/reverse-capitalization")
            ->assertStatus(403);
    }

    public function test_schema_has_no_prohibited_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('fixed_asset', 'branch_id'), 'fixed_asset must contain Phase 10 approved optional operational branch reference.');
        $this->assertTrue(Schema::hasColumn('fixed_asset', 'fixed_asset_location_id'), 'fixed_asset must contain Phase 10 approved optional operational fixed asset location reference.');

        $prohibited = [
            'company_id',
            'tenant_id',
            'custodian_id',
            'employee_id',
            'warehouse_id',
            'location_id',
            'supplier_bill_id',
            'purchase_order_id',
        ];

        foreach ($prohibited as $col) {
            $this->assertFalse(Schema::hasColumn('fixed_asset', $col), "fixed_asset must NOT contain column [{$col}].");
        }
    }
}
