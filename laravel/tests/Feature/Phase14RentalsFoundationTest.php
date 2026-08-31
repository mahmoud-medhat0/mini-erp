<?php

namespace Tests\Feature;

use App\Application\Rentals\RentableItemService;
use App\Application\Rentals\RentalContractService;
use App\Application\Rentals\RentalFulfillmentService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RentableItem;
use App\Models\RentableItemStatusEvent;
use App\Models\RentalContract;
use App\Models\RentalContractStatusEvent;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase14RentalsFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'rentals.view',
            'rentals.create',
            'rentals.edit',
            'rentals.delete',
            'rentals.submit',
            'rentals.approve',
            'rentals.deliver',
            'rentals.return',
            'rentals.inspect',
            'rentals.cancel',
        ]);
        $this->actingAs($this->user);

        $this->branch = Branch::query()->firstOrCreate(
            ['code' => 'RENT-BR'],
            ['name' => ['en' => 'Rental Branch', 'ar' => 'فرع التأجير'], 'is_active' => true]
        );

        $this->warehouse = Warehouse::query()->create([
            'code' => 'RENT-WH',
            'name' => ['en' => 'Rental Warehouse', 'ar' => 'مخزن التأجير'],
            'branch_id' => $this->branch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_phase14_rentable_item_schema_has_no_tenant_or_company_scope(): void
    {
        $this->assertTrue(Schema::hasTable('rentable_item'));
        $this->assertTrue(Schema::hasTable('rentable_item_status_event'));
        $this->assertTrue(Schema::hasTable('rental_contract'));
        $this->assertTrue(Schema::hasTable('rental_contract_line'));
        $this->assertTrue(Schema::hasTable('rental_contract_status_event'));
        $this->assertTrue(Schema::hasTable('rental_handover'));
        $this->assertTrue(Schema::hasTable('rental_handover_line'));
        $this->assertTrue(Schema::hasTable('rental_return'));
        $this->assertTrue(Schema::hasTable('rental_return_line'));
        $this->assertFalse(Schema::hasColumn('rentable_item', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rentable_item', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('rentable_item_status_event', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rentable_item_status_event', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('rental_contract', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_contract', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('rental_contract_line', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_contract_line', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('rental_handover', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_handover', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('rental_return', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_return', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('rentable_item', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('rentable_item', 'warehouse_id'));
        $this->assertTrue(Schema::hasColumn('rental_contract', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('rental_handover', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('rental_return', 'branch_id'));
        $this->assertFalse(config('permission.teams'));
    }

    public function test_can_create_standalone_rentable_item_with_operational_location_and_audit_event(): void
    {
        $item = $this->createRentableItem();

        $this->assertSame('RENT-ITEM-001', $item->code);
        $this->assertSame('standalone', $item->item_source);
        $this->assertSame($this->branch->id, $item->branch_id);
        $this->assertSame($this->warehouse->id, $item->warehouse_id);
        $this->assertSame('available', $item->status);
        $this->assertTrue($item->statusEvents()->where('event_type', 'created')->exists());
        $this->assertDatabaseHas('activity_log', [
            'event' => 'rentable_item.create',
        ]);
    }

    public function test_source_reference_rules_allow_exactly_one_configured_source(): void
    {
        $service = app(RentableItemService::class);
        $product = $this->createProduct();
        $fixedAsset = $this->createFixedAsset();

        $productItem = $service->create([
            ...$this->basePayload(['code' => 'RENT-PROD-001']),
            'item_source' => 'product',
            'product_id' => $product->id,
        ], $this->user->id);

        $fixedAssetItem = $service->create([
            ...$this->basePayload(['code' => 'RENT-FA-001']),
            'item_source' => 'fixed_asset',
            'fixed_asset_id' => $fixedAsset->id,
        ], $this->user->id);

        $this->assertTrue($product->fresh()->rentableItems()->whereKey($productItem->id)->exists());
        $this->assertTrue($fixedAsset->fresh()->rentableItems()->whereKey($fixedAssetItem->id)->exists());

        try {
            $service->create([
                ...$this->basePayload(['code' => 'RENT-BAD-001']),
                'item_source' => 'product',
            ], $this->user->id);
            $this->fail('Product-sourced rentable items must require a product reference.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_id', $exception->errors());
        }

        try {
            $service->create([
                ...$this->basePayload(['code' => 'RENT-BAD-002']),
                'item_source' => 'standalone',
                'product_id' => $product->id,
            ], $this->user->id);
            $this->fail('Standalone rentable items must reject source references.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item_source', $exception->errors());
        }
    }

    public function test_warehouse_branch_mismatch_is_rejected_as_operational_consistency_rule(): void
    {
        $otherBranch = Branch::query()->create([
            'code' => 'RENT-BR-2',
            'name' => ['en' => 'Other Rental Branch', 'ar' => 'فرع تأجير آخر'],
            'is_active' => true,
        ]);
        $otherWarehouse = Warehouse::query()->create([
            'code' => 'RENT-WH-2',
            'name' => ['en' => 'Other Rental Warehouse', 'ar' => 'مخزن تأجير آخر'],
            'branch_id' => $otherBranch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        try {
            $this->createRentableItem([
                'code' => 'RENT-MISMATCH',
                'warehouse_id' => $otherWarehouse->id,
            ]);
            $this->fail('Warehouse and branch mismatch must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('warehouse_id', $exception->errors());
        }
    }

    public function test_status_updates_create_history_and_active_workflow_items_cannot_be_deleted(): void
    {
        $service = app(RentableItemService::class);
        $item = $this->createRentableItem();

        $updated = $service->update($item->id, [
            'status' => 'rented',
            'reason' => 'Allocated to an active rental contract draft.',
            'lock_version' => $item->lock_version,
        ], $this->user->id);

        $this->assertSame('rented', $updated->status);
        $this->assertTrue(RentableItemStatusEvent::query()
            ->where('rentable_item_id', $item->id)
            ->where('from_status', 'available')
            ->where('to_status', 'rented')
            ->where('event_type', 'status_changed')
            ->exists());

        $this->expectException(ValidationException::class);
        $service->delete($updated->id, $this->user->id);
    }

    public function test_rentable_items_page_requires_permission_and_renders_reference_lists(): void
    {
        $this->withoutVite();

        $limited = User::factory()->create();
        $this->actingAs($limited)->get('/rentals/items')->assertForbidden();

        $this->actingAs($this->user);
        $this->createRentableItem();

        $this->get('/rentals/items')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rentals/RentableItems')
                ->has('items.data', 1)
                ->has('branches')
                ->has('warehouses')
                ->has('products')
                ->has('fixedAssets')
                ->has('currencies')
                ->where('itemSources.0', 'standalone'));
    }

    public function test_attachment_registry_supports_rentable_items_without_company_or_tenant_scope(): void
    {
        foreach (['rentable_item', 'rental_contract', 'rental_handover', 'rental_return'] as $entityType) {
            $entity = config("erp_attachments.entities.{$entityType}");

            $this->assertSame($entityType, $entity['table']);
            $this->assertSame(['rentals.view'], $entity['permissions']['view']);
            $this->assertNotEmpty($entity['permissions']['attach']);
            $this->assertNotEmpty($entity['permissions']['delete']);
        }
    }

    public function test_can_create_rental_contract_and_calculate_estimated_totals_without_gl_posting(): void
    {
        $contract = $this->createRentalContract();

        $this->assertSame('draft', $contract->status);
        $this->assertNull($contract->number);
        $this->assertSame(50000, $contract->estimated_rent_minor);
        $this->assertSame(10000, $contract->deposit_minor);
        $this->assertSame(60000, $contract->total_estimated_minor);
        $this->assertCount(1, $contract->lines);
        $this->assertSame(1, $contract->customer->rentalContracts()->count());
        $this->assertTrue(RentalContractStatusEvent::query()->where('rental_contract_id', $contract->id)->where('event_type', 'created')->exists());
        $this->assertDatabaseHas('activity_log', ['event' => 'rental_contract.create']);
    }

    public function test_contract_lifecycle_reserves_allocates_and_rents_items_with_numbering(): void
    {
        $service = app(RentalContractService::class);
        $contract = $this->createRentalContract();
        $itemId = $contract->lines->first()->rentable_item_id;

        $submitted = $service->submit($contract->id, $this->user->id);
        $this->assertSame('submitted', $submitted->status);
        $this->assertStringStartsWith('RENT-2026-', (string) $submitted->number);
        $this->assertSame('reserved', RentableItem::query()->findOrFail($itemId)->status);

        $approved = $service->approve($submitted->id, $this->user->id);
        $this->assertSame('approved', $approved->status);
        $this->assertSame('allocated', RentableItem::query()->findOrFail($itemId)->status);

        $active = $service->activate($approved->id, $this->user->id);
        $this->assertSame('active', $active->status);
        $this->assertSame('rented', RentableItem::query()->findOrFail($itemId)->status);
        $this->assertTrue(RentalContractStatusEvent::query()->where('rental_contract_id', $contract->id)->where('event_type', 'activated')->exists());
        $this->assertTrue(RentableItemStatusEvent::query()->where('rentable_item_id', $itemId)->where('to_status', 'rented')->exists());
    }

    public function test_cancelling_submitted_or_approved_contract_releases_reserved_items(): void
    {
        $service = app(RentalContractService::class);
        $contract = $this->createRentalContract();
        $itemId = $contract->lines->first()->rentable_item_id;

        $submitted = $service->submit($contract->id, $this->user->id);
        $cancelled = $service->cancel($submitted->id, $this->user->id);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('available', RentableItem::query()->findOrFail($itemId)->status);
    }

    public function test_contract_rejects_duplicate_or_unavailable_rentable_items(): void
    {
        $item = $this->createRentableItem(['code' => 'RENT-DUP-001']);
        $customer = $this->createCustomer();
        $service = app(RentalContractService::class);

        try {
            $service->create($this->contractPayload($customer, [
                'lines' => [
                    $this->contractLinePayload($item),
                    $this->contractLinePayload($item),
                ],
            ]), $this->user->id);
            $this->fail('Duplicate rentable item lines must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.1.rentable_item_id', $exception->errors());
        }

        app(RentableItemService::class)->update($item->id, [
            'status' => 'maintenance',
            'lock_version' => $item->fresh()->lock_version,
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        $service->create($this->contractPayload($customer, [
            'reference' => 'UNAVAILABLE',
            'lines' => [$this->contractLinePayload($item)],
        ]), $this->user->id);
    }

    public function test_rental_contracts_page_requires_permission_and_renders_reference_lists(): void
    {
        $this->withoutVite();

        $limited = User::factory()->create();
        $this->actingAs($limited)->get('/rentals/contracts')->assertForbidden();

        $this->actingAs($this->user);
        $this->createRentalContract();

        $this->get('/rentals/contracts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rentals/Contracts')
                ->has('contracts.data', 1)
                ->has('customers')
                ->has('branches')
                ->has('rentableItems')
                ->has('currencies')
                ->where('statuses.0', 'draft'));
    }

    public function test_rental_handover_confirms_contract_activation_and_item_rented_status_without_gl_posting(): void
    {
        $contractService = app(RentalContractService::class);
        $fulfillmentService = app(RentalFulfillmentService::class);
        $contract = $contractService->approve($contractService->submit($this->createRentalContract()->id, $this->user->id)->id, $this->user->id);
        $line = $contract->fresh('lines')->lines->first();
        $journalCount = JournalEntry::query()->count();

        $handover = $fulfillmentService->createHandover([
            'rental_contract_id' => $contract->id,
            'handover_date' => '2026-01-10',
            'notes' => 'Physical handover completed.',
            'lines' => [[
                'rental_contract_line_id' => $line->id,
                'condition_out' => 'good',
                'accessories_out' => ['Cable', 'Manual'],
                'notes' => 'Clean outgoing condition.',
            ]],
        ], $this->user->id);

        $confirmed = $fulfillmentService->confirmHandover($handover->id, $this->user->id);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertStringStartsWith('RH-2026-', (string) $confirmed->number);
        $this->assertSame('active', RentalContract::query()->findOrFail($contract->id)->status);
        $this->assertSame('rented', RentableItem::query()->findOrFail($line->rentable_item_id)->status);
        $this->assertSame($journalCount, JournalEntry::query()->count());
        $this->assertDatabaseHas('activity_log', ['event' => 'rental_handover.confirm']);
    }

    public function test_rental_return_submit_and_complete_updates_item_outcome_and_completes_contract(): void
    {
        $contractService = app(RentalContractService::class);
        $fulfillmentService = app(RentalFulfillmentService::class);
        $contract = $contractService->activate($contractService->approve($contractService->submit($this->createRentalContract()->id, $this->user->id)->id, $this->user->id)->id, $this->user->id);
        $line = $contract->fresh('lines')->lines->first();
        $journalCount = JournalEntry::query()->count();

        $return = $fulfillmentService->createReturn([
            'rental_contract_id' => $contract->id,
            'return_date' => '2026-02-10',
            'notes' => 'Returned for inspection.',
            'lines' => [[
                'rental_contract_line_id' => $line->id,
                'condition_in' => 'damaged',
                'outcome' => 'damaged',
                'estimated_damage_charge_minor' => 7500,
                'accessories_in' => ['Cable'],
                'inspection_notes' => 'Surface damage captured for later billing decision.',
            ]],
        ], $this->user->id);

        $submitted = $fulfillmentService->submitReturn($return->id, $this->user->id);
        $this->assertSame('submitted', $submitted->status);
        $this->assertStringStartsWith('RR-2026-', (string) $submitted->number);
        $this->assertSame('return_pending', RentableItem::query()->findOrFail($line->rentable_item_id)->status);

        $completed = $fulfillmentService->completeReturn($submitted->id, $this->user->id);

        $this->assertSame('completed', $completed->status);
        $this->assertSame('completed', RentalContract::query()->findOrFail($contract->id)->status);
        $this->assertSame('damaged', RentableItem::query()->findOrFail($line->rentable_item_id)->status);
        $this->assertSame(7500, $completed->lines->first()->estimated_damage_charge_minor);
        $this->assertSame($journalCount, JournalEntry::query()->count());
        $this->assertDatabaseHas('activity_log', ['event' => 'rental_return.complete']);
        $this->assertTrue(RentalContractStatusEvent::query()->where('rental_contract_id', $contract->id)->where('event_type', 'completed')->exists());
    }

    public function test_rental_return_rejects_non_active_contracts(): void
    {
        $contract = app(RentalContractService::class)->approve(app(RentalContractService::class)->submit($this->createRentalContract()->id, $this->user->id)->id, $this->user->id);
        $line = $contract->fresh('lines')->lines->first();

        $this->expectException(ValidationException::class);

        app(RentalFulfillmentService::class)->createReturn([
            'rental_contract_id' => $contract->id,
            'return_date' => '2026-02-10',
            'lines' => [[
                'rental_contract_line_id' => $line->id,
                'condition_in' => 'good',
                'outcome' => 'returned',
            ]],
        ], $this->user->id);
    }

    public function test_rental_handover_and_return_pages_require_permission_and_render_reference_lists(): void
    {
        $this->withoutVite();

        $limited = User::factory()->create();
        $this->actingAs($limited)->get('/rentals/handovers')->assertForbidden();
        $this->actingAs($limited)->get('/rentals/returns')->assertForbidden();

        $this->actingAs($this->user);
        $contract = app(RentalContractService::class)->activate(app(RentalContractService::class)->approve(app(RentalContractService::class)->submit($this->createRentalContract()->id, $this->user->id)->id, $this->user->id)->id, $this->user->id);
        $this->assertSame('active', $contract->status);

        $this->get('/rentals/handovers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rentals/Handovers')
                ->has('handovers.data')
                ->has('contracts')
                ->where('statuses.0', 'draft'));

        $this->get('/rentals/returns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rentals/Returns')
                ->has('returns.data')
                ->has('contracts')
                ->where('statuses.0', 'draft'));
    }

    private function createRentableItem(array $overrides = []): RentableItem
    {
        return app(RentableItemService::class)->create($this->basePayload($overrides), $this->user->id);
    }

    private function basePayload(array $overrides = []): array
    {
        return [
            'code' => 'RENT-ITEM-001',
            'name' => ['en' => 'Rental Excavator', 'ar' => 'حفار للتأجير'],
            'description' => ['en' => 'Standalone rentable item', 'ar' => 'عنصر تأجير مستقل'],
            'item_source' => 'standalone',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'available',
            'condition_status' => 'good',
            'currency' => 'EGP',
            'serial_number' => 'RENT-SN-001',
            'replacement_value_minor' => 500000,
            'daily_rate_minor' => 25000,
            'monthly_rate_minor' => 500000,
            'deposit_minor' => 100000,
            'notes' => 'Ready for rental.',
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function createProduct(): Product
    {
        return Product::query()->create([
            'code' => 'RENT-PRODUCT',
            'name' => ['en' => 'Rental Product', 'ar' => 'منتج تأجير'],
            'type' => 'stock',
            'unit_of_measure_id' => UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail()->id,
            'product_category_id' => ProductCategory::query()->where('code', 'FG')->firstOrFail()->id,
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'lock_version' => 1,
        ]);
    }

    private function createFixedAsset(): FixedAsset
    {
        $category = FixedAssetCategory::query()->create([
            'code' => 'RENT-FA-CAT',
            'name' => ['en' => 'Rental Fixed Assets', 'ar' => 'أصول ثابتة للتأجير'],
            'useful_life_months' => 60,
            'salvage_value_minor' => 0,
            'is_active' => true,
        ]);

        return FixedAsset::query()->create([
            'asset_number' => 'FA-RENT-001',
            'name' => ['en' => 'Rental Fixed Asset', 'ar' => 'أصل ثابت للتأجير'],
            'fixed_asset_category_id' => $category->id,
            'currency' => 'EGP',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
            'cost_minor' => 750000,
            'salvage_value_minor' => 0,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'opening_accumulated_depreciation_minor' => 0,
            'status' => 'active',
            'branch_id' => $this->branch->id,
            'lock_version' => 1,
        ]);
    }

    private function createCustomer(array $overrides = []): Customer
    {
        return Customer::query()->create([
            'code' => 'CUST-RENT',
            'name' => ['en' => 'Rental Customer', 'ar' => 'عميل تأجير'],
            'status' => 'active',
            'lock_version' => 1,
            ...$overrides,
        ]);
    }

    private function createRentalContract(array $overrides = []): RentalContract
    {
        $item = $this->createRentableItem(['code' => $overrides['item_code'] ?? 'RENT-CONTRACT-ITEM']);
        $customer = $this->createCustomer(['code' => $overrides['customer_code'] ?? 'CUST-RENT']);

        return app(RentalContractService::class)->create($this->contractPayload($customer, [
            'lines' => [$this->contractLinePayload($item)],
            ...$overrides,
        ]), $this->user->id);
    }

    private function contractPayload(Customer $customer, array $overrides = []): array
    {
        return [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'contract_date' => '2026-01-05',
            'start_date' => '2026-01-10',
            'expected_end_date' => '2026-02-10',
            'currency' => 'EGP',
            'billing_cycle' => 'monthly',
            'reference' => 'RENT-REF',
            'notes' => 'Rental contract fixture.',
            ...$overrides,
        ];
    }

    private function contractLinePayload(RentableItem $item, array $overrides = []): array
    {
        return [
            'rentable_item_id' => $item->id,
            'description' => ['en' => 'Rental line', 'ar' => 'سطر تأجير'],
            'start_date' => '2026-01-10',
            'end_date' => '2026-02-10',
            'rate_type' => 'monthly',
            'rate_minor' => 50000,
            'estimated_units' => 1,
            'deposit_minor' => 10000,
            'notes' => 'Line fixture.',
            ...$overrides,
        ];
    }
}
