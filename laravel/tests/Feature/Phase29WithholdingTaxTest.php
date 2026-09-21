<?php

namespace Tests\Feature;

use App\Application\Taxes\WithholdingTaxService;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WithholdingTaxEntry;
use Database\Seeders\AccountantAcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 29 - Withholding Tax (WHT) technical framework
 * (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §8). No real Egyptian rates are
 * asserted here - only that the framework computes and posts correctly for
 * whatever code/rate an accountant configures, exactly as VAT already does.
 */
class Phase29WithholdingTaxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private TaxCode $whtCode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountantAcceptanceSeeder::class);
        $this->withoutVite();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo(['taxes.view', 'taxes.edit', 'taxes.file']);
        $this->actingAs($this->user);

        $this->supplier = Supplier::query()->where('code', 'ACC-SUPP-001')->firstOrFail();

        $this->whtCode = TaxCode::query()->create([
            'code' => 'WHT_TEST_5',
            'name' => ['en' => 'Test Withholding 5%', 'ar' => 'استقطاع اختباري 5%'],
            'tax_type' => 'withholding',
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'none',
            'is_active' => true,
        ]);

        TaxRate::query()->create([
            'tax_code_id' => $this->whtCode->id,
            'rate_bps' => 500,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
    }

    public function test_creating_an_entry_computes_the_withheld_amount_from_the_configured_rate(): void
    {
        $entry = app(WithholdingTaxService::class)->create([
            'tax_code_id' => $this->whtCode->id,
            'supplier_id' => $this->supplier->id,
            'entry_date' => '2026-01-10',
            'currency' => 'EGP',
            'base_amount_minor' => 1_000_000,
        ], $this->user->id);

        $this->assertSame('draft', $entry->status);
        $this->assertSame(500, $entry->rate_bps);
        $this->assertSame(50_000, $entry->withheld_amount_minor);
    }

    public function test_posting_an_entry_creates_a_balanced_journal_entry(): void
    {
        $service = app(WithholdingTaxService::class);
        $entry = $service->create([
            'tax_code_id' => $this->whtCode->id,
            'supplier_id' => $this->supplier->id,
            'entry_date' => '2026-01-10',
            'currency' => 'EGP',
            'base_amount_minor' => 2_000_000,
        ], $this->user->id);

        $posted = $service->post($entry->id, $this->user->id);

        $this->assertSame('posted', $posted->status);
        $this->assertNotNull($posted->number);
        $this->assertNotNull($posted->journal_entry_id);

        $journal = JournalEntry::query()->with('lines.account')->findOrFail($posted->journal_entry_id);
        $this->assertSame('withholding_tax_entry', $journal->source_type);
        $this->assertSame('posted', $journal->status);
        $this->assertSame(100_000, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(100_000, (int) $journal->lines->sum('credit_minor'));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '2100' && (int) $line->debit_minor === 100_000), 'AP control must be debited (reduces what we owe the supplier).');
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '2630' && (int) $line->credit_minor === 100_000), 'Withholding tax payable must be credited.');
    }

    public function test_posting_twice_is_idempotent(): void
    {
        $service = app(WithholdingTaxService::class);
        $entry = $service->create([
            'tax_code_id' => $this->whtCode->id,
            'supplier_id' => $this->supplier->id,
            'entry_date' => '2026-01-10',
            'currency' => 'EGP',
            'base_amount_minor' => 1_000_000,
        ], $this->user->id);

        $first = $service->post($entry->id, $this->user->id);
        $second = $service->post($entry->id, $this->user->id);

        $this->assertSame($first->journal_entry_id, $second->journal_entry_id);
        $this->assertSame(1, JournalEntry::query()->where('source_type', 'withholding_tax_entry')->where('source_id', $entry->id)->count());
    }

    public function test_a_vat_tax_code_cannot_be_used_for_a_withholding_entry(): void
    {
        $vatCode = TaxCode::query()->where('tax_type', 'vat')->where('is_active', true)->firstOrFail();

        $this->expectException(ValidationException::class);
        app(WithholdingTaxService::class)->create([
            'tax_code_id' => $vatCode->id,
            'supplier_id' => $this->supplier->id,
            'entry_date' => '2026-01-10',
            'currency' => 'EGP',
            'base_amount_minor' => 1_000_000,
        ], $this->user->id);
    }

    public function test_a_posted_entry_cannot_be_cancelled(): void
    {
        $service = app(WithholdingTaxService::class);
        $entry = $service->create([
            'tax_code_id' => $this->whtCode->id,
            'supplier_id' => $this->supplier->id,
            'entry_date' => '2026-01-10',
            'currency' => 'EGP',
            'base_amount_minor' => 1_000_000,
        ], $this->user->id);
        $service->post($entry->id, $this->user->id);

        $this->expectException(ValidationException::class);
        $service->cancel($entry->id, $this->user->id);
    }

    public function test_withholding_routes_require_tax_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('dashboard.view');
        $this->actingAs($viewer)->get('/taxes/withholding')->assertForbidden();

        $this->actingAs($this->user)->get('/taxes/withholding')->assertOk();

        $this->actingAs($this->user)->post('/taxes/withholding', [
            'tax_code_id' => $this->whtCode->id,
            'supplier_id' => $this->supplier->id,
            'entry_date' => '2026-01-10',
            'currency' => 'EGP',
            'base_amount_minor' => 1_000_000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('withholding_tax_entry', ['supplier_id' => $this->supplier->id, 'status' => 'draft']);

        // Editing a draft entry does not implicitly grant posting (taxes.file).
        $entry = WithholdingTaxEntry::query()->firstOrFail();
        $editorOnly = User::factory()->create();
        $editorOnly->givePermissionTo(['taxes.view', 'taxes.edit']);
        $this->actingAs($editorOnly)->post("/taxes/withholding/{$entry->id}/post")->assertForbidden();
    }
}
