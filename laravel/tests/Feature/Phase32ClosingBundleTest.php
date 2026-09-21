<?php

namespace Tests\Feature;

use App\Application\Accounting\CustomerReceiptService;
use App\Application\Accounting\SupplierPaymentService;
use App\Application\Catalog\ProductService;
use App\Application\Partners\PartnerService;
use App\Application\Partners\PartnerTransactionService;
use App\Application\Reports\EquityStatementReportService;
use App\Application\Reports\ReorderLevelReportService;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 32 - Closing bundle (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §11):
 * three independent low-effort items - Statement of Changes in Equity
 * (32.1, built on Phase 30), an explicit advance classification on receipts
 * and payments (32.2), and barcode/reorder level on products with a manual
 * below-minimum-stock report (32.3).
 */
class Phase32ClosingBundleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CashAccount $cashAccount;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);
        $this->withoutVite();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'partners.view', 'partners.create', 'partners.post', 'view_financials', 'reports.view',
            'customers.view', 'customers.receipts', 'suppliers.view', 'suppliers.payments',
            'products.view', 'products.create', 'products.edit',
        ]);
        $this->actingAs($this->user);

        $cashGlAccount = Account::query()->where('code', '1100')->firstOrFail();
        $this->cashAccount = CashAccount::query()->create([
            'code' => 'PHASE32-CASH-01',
            'name' => ['en' => 'Phase 32 Test Cash', 'ar' => 'خزينة اختبار المرحلة 32'],
            'gl_account_id' => $cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->fiscalYear = FiscalYear::query()->where('year', 2026)->firstOrFail();
        $this->period = FinancialPeriod::query()
            ->where('fiscal_year_id', $this->fiscalYear->id)
            ->where('start_date', '<=', '2026-01-05')
            ->where('end_date', '>=', '2026-01-05')
            ->firstOrFail();
    }

    public function test_equity_statement_reflects_posted_partner_contributions(): void
    {
        $partner = app(PartnerService::class)->create([
            'code' => 'EQ-PTR-001',
            'name' => ['en' => 'Equity Test Partner', 'ar' => 'شريك اختبار حقوق الملكية'],
            'share_bps' => 10000,
            'status' => 'active',
        ], $this->user->id);

        $transactionService = app(PartnerTransactionService::class);
        $transaction = $transactionService->create([
            'partner_id' => $partner->id,
            'transaction_type' => 'contribution',
            'transaction_date' => '2026-01-05',
            'currency' => 'EGP',
            'amount_minor' => 400_000,
        ], $this->user->id);

        $transactionService->post($transaction->id, [
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $report = app(EquityStatementReportService::class)->generate('2026-01-01', '2026-01-31');

        $this->assertSame(400_000, $report['contributions_minor']);
        $this->assertSame(0, $report['drawings_minor']);
        $this->assertSame(0, $report['distributions_minor']);
        $this->assertTrue($report['is_reconciled'], 'A statement with only a posted partner contribution and no other equity postings must reconcile exactly.');
        $this->assertSame($report['actual_closing_equity_minor'], $report['computed_closing_equity_minor']);
    }

    public function test_equity_statement_report_route_requires_view_financials_permission(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo('reports.view');
        $this->actingAs($limited)->get('/reports/equity-statement')->assertForbidden();

        $this->actingAs($this->user)->get('/reports/equity-statement')->assertOk();
    }

    public function test_customer_receipt_persists_the_is_advance_flag(): void
    {
        $customer = Customer::query()->create([
            'code' => 'ADV-CUST-001',
            'name' => ['en' => 'Advance Customer', 'ar' => 'عميل دفعة مقدمة'],
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $receipt = app(CustomerReceiptService::class)->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-01-05',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 100_000,
            'is_advance' => true,
        ], $this->user->id);

        $this->assertTrue($receipt->fresh()->is_advance);

        $notAdvance = app(CustomerReceiptService::class)->create([
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-01-05',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 50_000,
        ], $this->user->id);

        $this->assertFalse($notAdvance->fresh()->is_advance, 'is_advance must default to false when not explicitly set.');
    }

    public function test_supplier_payment_persists_the_is_advance_flag(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'ADV-SUPP-001',
            'name' => ['en' => 'Advance Supplier', 'ar' => 'مورد دفعة مقدمة'],
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $payment = app(SupplierPaymentService::class)->create([
            'supplier_id' => $supplier->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'payment_date' => '2026-01-05',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 75_000,
            'is_advance' => true,
        ], $this->user->id);

        $this->assertTrue($payment->fresh()->is_advance);
    }

    public function test_customer_receipt_route_accepts_is_advance(): void
    {
        $customer = Customer::query()->create([
            'code' => 'ADV-CUST-ROUTE',
            'name' => ['en' => 'Route Customer', 'ar' => 'عميل المسار'],
            'status' => 'active',
            'lock_version' => 1,
        ]);

        $this->actingAs($this->user)->post('/customer-receipts', [
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'financial_period_id' => $this->period->id,
            'receipt_date' => '2026-01-05',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'amount_minor' => 60_000,
            'is_advance' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_receipt', ['customer_id' => $customer->id, 'is_advance' => true]);
    }

    public function test_barcode_must_be_unique_when_provided(): void
    {
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();

        app(ProductService::class)->create([
            'code' => 'BC-PRD-001',
            'name' => ['en' => 'Barcoded Product', 'ar' => 'منتج بباركود'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'status' => 'active',
            'barcode' => 'BC-0001',
        ], $this->user->id);

        $this->expectException(ValidationException::class);
        app(ProductService::class)->create([
            'code' => 'BC-PRD-002',
            'name' => ['en' => 'Duplicate Barcode Product', 'ar' => 'منتج باركود مكرر'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'status' => 'active',
            'barcode' => 'BC-0001',
        ], $this->user->id);
    }

    public function test_reorder_level_cannot_be_negative(): void
    {
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(ProductService::class)->create([
            'code' => 'NEG-REORDER-001',
            'name' => ['en' => 'Negative Reorder', 'ar' => 'حد سالب'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'status' => 'active',
            'reorder_level' => -5,
        ], $this->user->id);
    }

    public function test_reorder_level_report_lists_only_products_below_their_minimum(): void
    {
        $uom = UnitOfMeasure::query()->where('code', 'PCS')->firstOrFail();
        $warehouse = Warehouse::query()->first();
        $this->assertNotNull($warehouse, 'Seeded database must have at least one warehouse.');

        $lowStock = Product::query()->create([
            'code' => 'LOW-STOCK-001',
            'name' => ['en' => 'Low Stock Item', 'ar' => 'صنف منخفض المخزون'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'status' => 'active',
            'reorder_level' => 100,
            'lock_version' => 1,
        ]);
        StockBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $lowStock->id,
            'unit_of_measure_id' => $uom->id,
            'currency' => 'EGP',
            'quantity_e6' => 30_000_000,
            'valuation_amount_minor' => 0,
            'avg_unit_cost_e6' => 0,
            'lock_version' => 1,
        ]);

        $wellStocked = Product::query()->create([
            'code' => 'WELL-STOCKED-001',
            'name' => ['en' => 'Well Stocked Item', 'ar' => 'صنف مخزونه كافٍ'],
            'type' => 'stock',
            'unit_of_measure_id' => $uom->id,
            'status' => 'active',
            'reorder_level' => 100,
            'lock_version' => 1,
        ]);
        StockBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $wellStocked->id,
            'unit_of_measure_id' => $uom->id,
            'currency' => 'EGP',
            'quantity_e6' => 500_000_000,
            'valuation_amount_minor' => 0,
            'avg_unit_cost_e6' => 0,
            'lock_version' => 1,
        ]);

        $report = app(ReorderLevelReportService::class)->generate();
        $codes = array_column($report['items'], 'code');

        $this->assertContains('LOW-STOCK-001', $codes);
        $this->assertNotContains('WELL-STOCKED-001', $codes);

        $lowItem = collect($report['items'])->firstWhere('code', 'LOW-STOCK-001');
        $this->assertEquals(30, $lowItem['on_hand_quantity']);
        $this->assertSame(100, $lowItem['reorder_level']);
        $this->assertEquals(70, $lowItem['shortfall']);
    }

    public function test_reorder_level_report_route_requires_view_financials_permission(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo('reports.view');
        $this->actingAs($limited)->get('/reports/reorder-level')->assertForbidden();

        $this->actingAs($this->user)->get('/reports/reorder-level')->assertOk();
    }
}
