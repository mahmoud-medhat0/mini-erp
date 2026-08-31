<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Inventory\MovingWeightedAverageInventoryService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Branch;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10BranchSpecificGlMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Warehouse $warehouse;

    private Product $product;

    private UnitOfMeasure $uom;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            RbacSeeder::class,
            AccountCategorySeeder::class,
            AccountTypeSeeder::class,
            AccountingCoreSeeder::class,
            UnitOfMeasureSeeder::class,
            ProductCategorySeeder::class,
            WarehouseSeeder::class,
        ]);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo('accounting.mappings');

        $this->branch = Branch::query()->create([
            'code' => 'BR-GL-OVR',
            'name' => ['en' => 'Branch GL Override', 'ar' => 'فرع مابنج الحسابات'],
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'code' => 'BR-GL-WH',
            'name' => ['en' => 'Branch GL Warehouse', 'ar' => 'مخزن مابنج الفرع'],
            'branch_id' => $this->branch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $category = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        $this->product = Product::query()->create([
            'code' => 'BR-GL-STOCK',
            'name' => ['en' => 'Branch GL Stock', 'ar' => 'مخزون مابنج الفرع'],
            'type' => 'stock',
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $category->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->fiscalYear = FiscalYear::query()->create([
            'year' => 2052,
            'start_date' => '2052-01-01',
            'end_date' => '2052-12-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'month' => 1,
            'start_date' => '2052-01-01',
            'end_date' => '2052-01-31',
            'status' => 'open',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);
    }

    public function test_account_mapping_supports_optional_branch_override_without_tenant_scope(): void
    {
        $this->assertTrue(Schema::hasColumn('accounting_account_mapping', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('accounting_account_mapping', 'company_id'));
        $this->assertFalse(Schema::hasColumn('accounting_account_mapping', 'tenant_id'));

        $service = app(AccountingAccountMappingService::class);
        $globalInventoryAccount = $service->getAccount('inventory_asset');
        $branchInventoryAccount = $this->createAccount('BR1400', 'Branch Inventory Asset', 'asset', 'debit');

        $firstMapping = $service->setMapping(
            key: 'inventory_asset',
            accountId: $branchInventoryAccount->id,
            description: 'Branch inventory override',
            actorId: $this->user->id,
            branchId: $this->branch->id,
        );

        $updatedMapping = $service->setMapping(
            key: 'inventory_asset',
            accountId: $branchInventoryAccount->id,
            description: 'Branch inventory override updated',
            actorId: $this->user->id,
            branchId: $this->branch->id,
        );

        $this->assertSame($firstMapping->id, $updatedMapping->id);
        $this->assertSame($branchInventoryAccount->id, $service->getAccount('inventory_asset', $this->branch->id)->id);
        $this->assertSame($globalInventoryAccount->id, $service->getAccount('inventory_asset')->id);
        $this->assertSame(1, AccountingAccountMapping::query()
            ->where('key', 'inventory_asset')
            ->where('branch_id', $this->branch->id)
            ->count());
    }

    public function test_branch_specific_inventory_mappings_are_used_for_warehouse_posting(): void
    {
        $service = app(AccountingAccountMappingService::class);
        $branchInventoryAccount = $this->createAccount('BR1401', 'Branch Inventory Posting', 'asset', 'debit');
        $branchGrniAccount = $this->createAccount('BR2301', 'Branch GRNI Posting', 'liability', 'credit');

        $service->setMapping('inventory_asset', $branchInventoryAccount->id, 'Branch inventory posting', $this->user->id, $this->branch->id);
        $service->setMapping('grni_clearing', $branchGrniAccount->id, 'Branch GRNI posting', $this->user->id, $this->branch->id);

        $movement = app(MovingWeightedAverageInventoryService::class)->recordReceipt(
            sourceType: 'branch_mapping_receipt',
            sourceId: (string) Str::uuid(),
            sourceLineId: (string) Str::uuid(),
            movementDate: '2052-01-10',
            productId: $this->product->id,
            unitOfMeasureId: $this->uom->id,
            currency: 'EGP',
            quantityE6: 5000000,
            unitCostMinor: 1200,
            fiscalYearId: $this->fiscalYear->id,
            financialPeriodId: $this->period->id,
            actorId: $this->user->id,
            warehouseId: $this->warehouse->id,
        );

        $ledgerAccountIds = LedgerEntry::query()
            ->where('journal_entry_id', $movement->journal_entry_id)
            ->pluck('account_id')
            ->all();

        $this->assertContains($branchInventoryAccount->id, $ledgerAccountIds);
        $this->assertContains($branchGrniAccount->id, $ledgerAccountIds);
        $this->assertSame(2, LedgerEntry::query()
            ->where('journal_entry_id', $movement->journal_entry_id)
            ->where('branch_id', $this->branch->id)
            ->count());
    }

    public function test_branch_mapping_rejects_unknown_branch_id(): void
    {
        $this->expectException(ValidationException::class);

        app(AccountingAccountMappingService::class)->setMapping(
            key: 'inventory_asset',
            accountId: $this->createAccount('BR1499', 'Invalid Branch Inventory', 'asset', 'debit')->id,
            branchId: (string) Str::uuid(),
        );
    }

    public function test_account_mapping_page_can_manage_branch_overrides(): void
    {
        $branchInventoryAccount = $this->createAccount('BR1402', 'Branch Inventory UI', 'asset', 'debit');

        $this->actingAs($this->user)
            ->get('/accounting/account-mappings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounting/AccountMappings')
                ->has('mappingKeys')
                ->has('mappings')
                ->has('accounts')
                ->has('branches')
            );

        $this->actingAs($this->user)
            ->post('/accounting/account-mappings', [
                'key' => 'inventory_asset',
                'branch_id' => $this->branch->id,
                'account_id' => $branchInventoryAccount->id,
                'description' => 'Branch UI override',
            ])
            ->assertRedirect();

        $mapping = AccountingAccountMapping::query()
            ->where('key', 'inventory_asset')
            ->where('branch_id', $this->branch->id)
            ->firstOrFail();

        $this->assertSame($branchInventoryAccount->id, $mapping->account_id);

        $this->actingAs($this->user)
            ->delete("/accounting/account-mappings/{$mapping->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('accounting_account_mapping', ['id' => $mapping->id]);
    }

    private function createAccount(string $code, string $name, string $type, string $nature): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'type' => $type,
            'nature' => $nature,
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }
}
