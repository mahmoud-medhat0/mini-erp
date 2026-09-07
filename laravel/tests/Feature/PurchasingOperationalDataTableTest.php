<?php

namespace Tests\Feature;

use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\LandedCostAllocation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierAdjustmentNote;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingOperationalDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'purchasing.view',
            'purchasing.returns',
            'purchasing.adjustment_notes',
            'purchasing.landed_costs',
        ]);

        $this->supplier = Supplier::query()->create([
            'code' => 'DT-SUP',
            'name' => ['en' => 'DataTable Supplier', 'ar' => 'مورد جدول البيانات'],
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::query()->create([
            'code' => 'DT-WH',
            'name' => ['en' => 'DataTable Warehouse', 'ar' => 'مخزن جدول البيانات'],
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
        ]);

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'number' => 'PO-DT-001',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-01',
            'currency' => 'EGP',
            'status' => 'confirmed',
            'reference' => 'PO-SEARCH',
            'total_minor' => 10000,
        ]);

        $goodsReceipt = GoodsReceipt::query()->create([
            'number' => 'GR-DT-001',
            'purchase_order_id' => $purchaseOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => '2026-09-02',
            'status' => 'confirmed',
            'reference' => 'GR-SEARCH',
        ]);

        SupplierBill::query()->create([
            'number' => 'BILL-DT-001',
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'goods_receipt_id' => $goodsReceipt->id,
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $period->id,
            'bill_date' => '2026-09-03',
            'currency' => 'EGP',
            'subtotal_minor' => 10000,
            'total_minor' => 10000,
            'status' => 'draft',
            'reference' => 'BILL-SEARCH',
        ]);

        $purchaseReturn = PurchaseReturn::query()->create([
            'number' => 'PR-DT-001',
            'supplier_id' => $this->supplier->id,
            'goods_receipt_id' => $goodsReceipt->id,
            'warehouse_id' => $this->warehouse->id,
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $period->id,
            'return_date' => '2026-09-04',
            'currency' => 'EGP',
            'status' => 'draft',
            'reason' => 'RETURN-SEARCH',
        ]);

        SupplierAdjustmentNote::query()->create([
            'number' => 'ADJ-DT-001',
            'supplier_id' => $this->supplier->id,
            'purchase_return_id' => $purchaseReturn->id,
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $period->id,
            'adjustment_date' => '2026-09-05',
            'direction' => 'decrease_payable',
            'currency' => 'EGP',
            'status' => 'draft',
            'reason' => 'ADJ-SEARCH',
        ]);

        LandedCostAllocation::query()->create([
            'number' => 'LC-DT-001',
            'goods_receipt_id' => $goodsReceipt->id,
            'supplier_id' => $this->supplier->id,
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $period->id,
            'allocation_date' => '2026-09-06',
            'currency' => 'EGP',
            'allocation_method' => 'by_value',
            'cost_amount_minor' => 1000,
            'total_amount_minor' => 1000,
            'status' => 'draft',
            'reference' => 'LC-SEARCH',
        ]);
    }

    public function test_purchasing_index_payloads_do_not_ship_operational_registers(): void
    {
        foreach ([
            ['/purchasing/orders', 'Purchasing/PurchaseOrders', 'purchaseOrders'],
            ['/purchasing/goods-receipts', 'Purchasing/GoodsReceipts', 'goodsReceipts'],
            ['/purchasing/bills', 'Purchasing/SupplierBills', 'supplierBills'],
            ['/purchasing/returns', 'Purchasing/PurchaseReturns', 'purchaseReturns'],
            ['/purchasing/adjustment-notes', 'Purchasing/SupplierAdjustmentNotes', 'supplierAdjustmentNotes'],
            ['/purchasing/landed-costs', 'Purchasing/LandedCosts', 'landedCosts'],
        ] as [$uri, $component, $prop]) {
            $this->actingAs($this->user)
                ->get($uri)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component)->where($prop, []));
        }
    }

    public function test_purchasing_datatables_search_and_filter_server_side(): void
    {
        $cases = [
            ['/purchasing/orders/data', 'order_date', ['status' => 'confirmed', 'supplier_id' => $this->supplier->id], 'PO-SEARCH', 'PO-DT-001'],
            ['/purchasing/goods-receipts/data', 'receipt_date', ['status' => 'confirmed', 'warehouse_id' => $this->warehouse->id], 'GR-SEARCH', 'GR-DT-001'],
            ['/purchasing/bills/data', 'bill_date', ['status' => 'draft'], 'BILL-SEARCH', 'BILL-DT-001'],
            ['/purchasing/returns/data', 'return_date', ['status' => 'draft', 'supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id], 'RETURN-SEARCH', 'PR-DT-001'],
            ['/purchasing/adjustment-notes/data', 'adjustment_date', ['status' => 'draft', 'supplier_id' => $this->supplier->id], 'ADJ-SEARCH', 'ADJ-DT-001'],
            ['/purchasing/landed-costs/data', 'allocation_date', ['status' => 'draft'], 'LC-SEARCH', 'LC-DT-001'],
        ];

        foreach ($cases as [$uri, $dateColumn, $filters, $search, $number]) {
            $query = array_merge($this->gridQuery($dateColumn, $search), $filters);

            $response = $this->actingAs($this->user)
                ->getJson($uri.'?'.http_build_query($query));

            $response->assertOk();
            $this->assertSame(1, $response->json('recordsFiltered'), "{$uri} did not return the filtered row: ".$response->getContent());
            $response->assertJsonPath('data.0.number', $number);
        }
    }

    public function test_purchasing_datatables_keep_route_permissions(): void
    {
        $stranger = User::factory()->create();

        foreach ([
            '/purchasing/orders/data',
            '/purchasing/goods-receipts/data',
            '/purchasing/bills/data',
            '/purchasing/returns/data',
            '/purchasing/adjustment-notes/data',
            '/purchasing/landed-costs/data',
        ] as $uri) {
            $this->actingAs($stranger)
                ->getJson($uri.'?'.http_build_query($this->gridQuery('created_at', '')))
                ->assertForbidden();
        }
    }

    /** @return array<string, mixed> */
    private function gridQuery(string $dateColumn, string $search): array
    {
        $columns = [
            ['number', true, true],
            [$dateColumn, false, true],
            ['status', false, true],
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
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }
}
