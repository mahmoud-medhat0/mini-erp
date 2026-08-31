<?php

namespace Tests\Feature;

use App\Application\Taxes\TaxCalculationService;
use App\Application\Taxes\TaxMasterDataService;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase7Slice2TaxFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unauthorizedUser;

    private TaxMasterDataService $masterService;

    private TaxCalculationService $calcService;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('taxes.view');
        Permission::findOrCreate('taxes.edit');

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo(['taxes.view', 'taxes.edit']);

        $this->unauthorizedUser = User::factory()->create();

        $this->masterService = app(TaxMasterDataService::class);
        $this->calcService = app(TaxCalculationService::class);
    }

    public function test_tax_tables_exist_without_unsupported_scope_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tax_codes'));
        $this->assertTrue(Schema::hasTable('tax_rates'));

        $prohibitedColumns = ['company_id', 'branch_id', 'tenant_id'];

        foreach ($prohibitedColumns as $col) {
            $this->assertFalse(Schema::hasColumn('tax_codes', $col));
            $this->assertFalse(Schema::hasColumn('tax_rates', $col));
        }
    }

    public function test_tax_code_crud_operations(): void
    {
        // Create
        $code = $this->masterService->createTaxCode([
            'code' => 'VAT_TEST_10',
            'name' => ['en' => 'Test VAT 10%', 'ar' => 'ضريبة تجريبية 10%'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);

        $this->assertDatabaseHas('tax_codes', [
            'id' => $code->id,
            'code' => 'VAT_TEST_10',
            'calculation_mode' => 'exclusive',
        ]);

        // Update
        $updated = $this->masterService->updateTaxCode($code->id, [
            'calculation_mode' => 'inclusive',
        ]);
        $this->assertEquals('inclusive', $updated->calculation_mode);

        // Delete
        $this->masterService->deleteTaxCode($code->id);
        $this->assertDatabaseMissing('tax_codes', ['id' => $code->id]);
    }

    public function test_system_tax_code_cannot_be_deleted(): void
    {
        $systemCode = TaxCode::query()->create([
            'code' => 'VAT_SYSTEM_14',
            'name' => ['en' => 'System Code', 'ar' => 'كود نظامي'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
            'is_system' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->masterService->deleteTaxCode($systemCode->id);
    }

    public function test_tax_code_with_rates_cannot_be_deleted(): void
    {
        $code = $this->masterService->createTaxCode([
            'code' => 'VAT_WITH_RATES',
            'name' => ['en' => 'VAT with Rates', 'ar' => 'ضريبة بنسب'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);

        $this->masterService->createTaxRate([
            'tax_code_id' => $code->id,
            'rate_bps' => 1400,
            'effective_from' => '2026-01-01',
        ]);

        $this->expectException(ValidationException::class);
        $this->masterService->deleteTaxCode($code->id);
    }

    public function test_tax_rate_effective_date_lookup(): void
    {
        $code = $this->masterService->createTaxCode([
            'code' => 'VAT_HISTORICAL',
            'name' => ['en' => 'Historical VAT', 'ar' => 'ضريبة تاريخية'],
            'calculation_mode' => 'exclusive',
        ]);

        // Old rate: 10% (1000 bps) from 2020-01-01 to 2024-12-31
        $oldRate = $this->masterService->createTaxRate([
            'tax_code_id' => $code->id,
            'rate_bps' => 1000,
            'effective_from' => '2020-01-01',
            'effective_to' => '2024-12-31',
        ]);

        // New rate: 14% (1400 bps) from 2025-01-01
        $newRate = $this->masterService->createTaxRate([
            'tax_code_id' => $code->id,
            'rate_bps' => 1400,
            'effective_from' => '2025-01-01',
        ]);

        // Lookup on 2023-06-15 -> Old rate
        $foundOld = $this->calcService->resolveEffectiveRate($code->id, '2023-06-15');
        $this->assertNotNull($foundOld);
        $this->assertEquals(1000, $foundOld->rate_bps);

        // Lookup on 2026-08-23 -> New rate
        $foundNew = $this->calcService->resolveEffectiveRate($code->id, '2026-08-23');
        $this->assertNotNull($foundNew);
        $this->assertEquals(1400, $foundNew->rate_bps);
    }

    public function test_exact_integer_tax_calculation_math(): void
    {
        // 1. Exclusive Tax (14% on $100.00 = 10000 minor) -> Tax = 1400 minor ($14.00), Gross = 11400 minor ($114.00)
        $exclusiveCode = $this->masterService->createTaxCode([
            'code' => 'VAT_EXCL_14',
            'name' => ['en' => 'Exclusive 14%', 'ar' => 'غير شاملة 14%'],
            'calculation_mode' => 'exclusive',
        ]);
        $this->masterService->createTaxRate([
            'tax_code_id' => $exclusiveCode->id,
            'rate_bps' => 1400,
            'effective_from' => '2020-01-01',
        ]);

        $exclResult = $this->calcService->calculateTax($exclusiveCode->id, 10000, '2026-08-23');
        $this->assertEquals(10000, $exclResult['taxable_base_minor']);
        $this->assertEquals(1400, $exclResult['tax_minor']);
        $this->assertEquals(11400, $exclResult['gross_minor']);
        $this->assertEquals(1400, $exclResult['rate_bps']);
        $this->assertEquals('half_up', $exclResult['rounding_policy']);

        // 2. Inclusive Tax (14% on $114.00 gross = 11400 minor) -> Net = 10000 minor ($100.00), Tax = 1400 minor ($14.00)
        $inclusiveCode = $this->masterService->createTaxCode([
            'code' => 'VAT_INCL_14',
            'name' => ['en' => 'Inclusive 14%', 'ar' => 'شاملة 14%'],
            'calculation_mode' => 'inclusive',
        ]);
        $this->masterService->createTaxRate([
            'tax_code_id' => $inclusiveCode->id,
            'rate_bps' => 1400,
            'effective_from' => '2020-01-01',
        ]);

        $inclResult = $this->calcService->calculateTax($inclusiveCode->id, 11400, '2026-08-23');
        $this->assertEquals(10000, $inclResult['taxable_base_minor']);
        $this->assertEquals(1400, $inclResult['tax_minor']);
        $this->assertEquals(11400, $inclResult['gross_minor']);

        // 3. Exempt Tax -> Tax = 0, Gross = Net
        $exemptCode = $this->masterService->createTaxCode([
            'code' => 'VAT_EXEMPT',
            'name' => ['en' => 'Exempt', 'ar' => 'معفاة'],
            'calculation_mode' => 'exempt',
        ]);
        $exemptResult = $this->calcService->calculateTax($exemptCode->id, 10000, '2026-08-23');
        $this->assertEquals(10000, $exemptResult['taxable_base_minor']);
        $this->assertEquals(0, $exemptResult['tax_minor']);
        $this->assertEquals(10000, $exemptResult['gross_minor']);
    }

    public function test_http_routes_permission_enforcement(): void
    {
        // Index view denied for unauthorized user
        $this->actingAs($this->unauthorizedUser)
            ->get(route('taxes.codes.index'))
            ->assertStatus(403);

        // Index view allowed for authorized user
        $this->actingAs($this->adminUser)
            ->get(route('taxes.codes.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Taxes/Codes/Index'));

        // Store denied for unauthorized user
        $this->actingAs($this->unauthorizedUser)
            ->post(route('taxes.codes.store'), [
                'code' => 'UNAUTH_CODE',
                'name' => ['en' => 'Unauth', 'ar' => 'غير مخول'],
                'calculation_mode' => 'exclusive',
                'recoverability_mode' => 'full',
            ])
            ->assertStatus(403);
    }
}
