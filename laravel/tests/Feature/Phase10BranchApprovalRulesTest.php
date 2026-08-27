<?php

namespace Tests\Feature;

use App\Application\Approvals\BranchApprovalRuleService;
use App\Application\Inventory\StockAdjustmentService;
use App\Application\Inventory\StockCountService;
use App\Application\Inventory\StockTransferService;
use App\Models\Branch;
use App\Models\BranchApprovalRule;
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
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase10BranchApprovalRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $sourceBranch;

    private Branch $destinationBranch;

    private Warehouse $sourceWarehouse;

    private Warehouse $destinationWarehouse;

    private Product $product;

    private UnitOfMeasure $uom;

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
        $this->user->givePermissionTo(['inventory.approve', 'approvals.configure', 'settings.configure']);

        $this->sourceBranch = $this->branch('BR-APR-SRC', 'Approval Source Branch');
        $this->destinationBranch = $this->branch('BR-APR-DST', 'Approval Destination Branch');
        $this->sourceWarehouse = $this->warehouse('APR-SRC-WH', 'Approval Source Warehouse', $this->sourceBranch);
        $this->destinationWarehouse = $this->warehouse('APR-DST-WH', 'Approval Destination Warehouse', $this->destinationBranch);

        $this->uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $category = ProductCategory::query()->where('code', 'FG')->firstOrFail();

        $this->product = Product::query()->create([
            'code' => 'APR-STOCK',
            'name' => ['en' => 'Approval Stock', 'ar' => 'مخزون الاعتماد'],
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
    }

    public function test_branch_approval_rule_schema_is_operational_not_tenant_scope(): void
    {
        $this->assertTrue(Schema::hasTable('branch_approval_rule'));
        $this->assertTrue(Schema::hasColumn('branch_approval_rule', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('branch_approval_rule', 'company_id'));
        $this->assertFalse(Schema::hasColumn('branch_approval_rule', 'tenant_id'));
        $this->assertFalse((bool) config('permission.teams'));
    }

    public function test_branch_approval_rules_page_can_manage_rules(): void
    {
        $this->actingAs($this->user)
            ->get('/settings/branch-approval-rules')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/BranchApprovalRules')
                ->has('rules')
                ->has('branches')
                ->has('documentTypes')
                ->has('branchMatches')
                ->has('permissionOptions')
            );

        $this->actingAs($this->user)
            ->post('/settings/branch-approval-rules', [
                'document_type' => 'stock_transfer',
                'branch_match' => 'source',
                'branch_id' => $this->sourceBranch->id,
                'required_permission' => 'approvals.override',
                'is_active' => true,
                'notes' => 'High control branch',
            ])
            ->assertRedirect();

        $rule = BranchApprovalRule::query()->where('document_type', 'stock_transfer')->firstOrFail();
        $this->assertSame($this->sourceBranch->id, $rule->branch_id);

        $this->actingAs($this->user)
            ->patch("/settings/branch-approval-rules/{$rule->id}", [
                'document_type' => 'stock_transfer',
                'branch_match' => 'destination',
                'branch_id' => $this->destinationBranch->id,
                'required_permission' => 'approvals.override',
                'is_active' => false,
                'notes' => 'Destination control branch',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branch_approval_rule', [
            'id' => $rule->id,
            'branch_match' => 'destination',
            'branch_id' => $this->destinationBranch->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->delete("/settings/branch-approval-rules/{$rule->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('branch_approval_rule', ['id' => $rule->id]);
    }

    public function test_stock_transfer_approval_requires_rule_permission_for_matching_branch(): void
    {
        app(BranchApprovalRuleService::class)->create([
            'document_type' => 'stock_transfer',
            'branch_match' => 'source',
            'branch_id' => $this->sourceBranch->id,
            'required_permission' => 'approvals.override',
            'is_active' => true,
        ], $this->user->id);

        $transfer = app(StockTransferService::class)->create([
            'transfer_date' => '2053-01-10',
            'source_warehouse_id' => $this->sourceWarehouse->id,
            'destination_warehouse_id' => $this->destinationWarehouse->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1000000,
            ]],
        ], $this->user->id);

        $this->expectException(ValidationException::class);

        app(StockTransferService::class)->approve($transfer->id, $this->user->id);
    }

    public function test_stock_transfer_approval_passes_after_required_rule_permission_is_granted(): void
    {
        app(BranchApprovalRuleService::class)->create([
            'document_type' => 'stock_transfer',
            'branch_match' => 'either',
            'branch_id' => $this->destinationBranch->id,
            'required_permission' => 'approvals.override',
            'is_active' => true,
        ], $this->user->id);

        $this->user->givePermissionTo('approvals.override');

        $transfer = app(StockTransferService::class)->create([
            'transfer_date' => '2053-01-10',
            'source_warehouse_id' => $this->sourceWarehouse->id,
            'destination_warehouse_id' => $this->destinationWarehouse->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_e6' => 1000000,
            ]],
        ], $this->user->id);

        $approved = app(StockTransferService::class)->approve($transfer->id, $this->user->id);

        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->number);
    }

    public function test_stock_count_and_adjustment_approval_rules_use_document_warehouse_branch(): void
    {
        app(BranchApprovalRuleService::class)->create([
            'document_type' => 'stock_count',
            'branch_match' => 'document',
            'branch_id' => $this->sourceBranch->id,
            'required_permission' => 'approvals.override',
            'is_active' => true,
        ], $this->user->id);

        app(BranchApprovalRuleService::class)->create([
            'document_type' => 'stock_adjustment',
            'branch_match' => 'document',
            'branch_id' => $this->sourceBranch->id,
            'required_permission' => 'approvals.override',
            'is_active' => true,
        ], $this->user->id);

        $count = app(StockCountService::class)->create([
            'count_date' => '2053-01-11',
            'warehouse_id' => $this->sourceWarehouse->id,
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'expected_quantity_e6' => 0,
                'counted_quantity_e6' => 1000000,
                'unit_cost_minor' => 1000,
            ]],
        ], $this->user->id);

        try {
            app(StockCountService::class)->approve($count->id, $this->user->id);
            $this->fail('Stock count approval should require the configured branch approval permission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval', $exception->errors());
        }

        $this->user->givePermissionTo('approvals.override');
        $this->assertSame('approved', app(StockCountService::class)->approve($count->id, $this->user->id)->status);

        $adjustment = app(StockAdjustmentService::class)->create([
            'adjustment_date' => '2053-01-12',
            'warehouse_id' => $this->sourceWarehouse->id,
            'currency' => 'EGP',
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_of_measure_id' => $this->uom->id,
                'quantity_delta_e6' => 1000000,
                'unit_cost_minor' => 1000,
            ]],
        ], $this->user->id);

        $this->assertSame('approved', app(StockAdjustmentService::class)->approve($adjustment->id, $this->user->id)->status);
    }

    private function branch(string $code, string $name): Branch
    {
        return Branch::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'is_active' => true,
        ]);
    }

    private function warehouse(string $code, string $name, Branch $branch): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'branch_id' => $branch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);
    }
}
