<?php

namespace Tests\Feature;

use App\Application\Rentals\RentableItemService;
use App\Application\Rentals\RentalContractService;
use App\Application\Rentals\RentalFulfillmentService;
use App\Application\Rentals\RentalInvoiceService;
use App\Application\Reports\RentalOperationsReportService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\RentableItem;
use App\Models\RentalContract;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase14RentalReportsCloseOutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Warehouse $warehouse;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'reports.view',
            'reports.export',
            'reports.print',
            'view_financials',
            'rentals.view',
            'rentals.create',
            'rentals.edit',
            'rentals.submit',
            'rentals.approve',
            'rentals.post',
            'rentals.invoice',
            'rentals.deliver',
            'rentals.return',
            'rentals.inspect',
            'rentals.cancel',
        ]);
        $this->actingAs($this->user);

        $this->branch = Branch::query()->firstOrCreate(
            ['code' => 'RENT-REP-BR'],
            ['name' => ['en' => 'Rental Report Branch', 'ar' => 'فرع تقرير الإيجار'], 'is_active' => true]
        );

        $this->warehouse = Warehouse::query()->create([
            'code' => 'RENT-REP-WH',
            'name' => ['en' => 'Rental Report Warehouse', 'ar' => 'مخزن تقرير الإيجار'],
            'branch_id' => $this->branch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_rental_operations_report_summarizes_billing_readiness_and_pending_damage(): void
    {
        $overdueContract = $this->activeContract();
        $postedInvoice = $this->postRentInvoice($overdueContract);
        $damageContract = $this->activeContract();
        $this->completedReturn($damageContract, 7500);

        $report = app(RentalOperationsReportService::class)->generate([
            'as_of_date' => '2026-02-20',
            'date_from' => '2026-01-01',
            'date_to' => '2026-02-28',
            'branch_id' => $this->branch->id,
            'currency' => 'EGP',
        ]);

        $rows = collect($report['rows']);
        $overdueRow = $rows->firstWhere('contract_id', $overdueContract->id);
        $damageRow = $rows->firstWhere('contract_id', $damageContract->id);

        $this->assertNotNull($overdueRow);
        $this->assertNotNull($damageRow);
        $this->assertSame('overdue', $overdueRow['due_state']);
        $this->assertSame(1, $overdueRow['posted_invoice_count']);
        $this->assertSame(57000, $overdueRow['total_billed_minor']);
        $this->assertSame($postedInvoice->journalEntry->number, $overdueRow['latest_journal_number']);
        $this->assertSame(7500, $damageRow['pending_damage_minor']);
        $this->assertSame(1, $damageRow['unbilled_line_count']);

        $this->assertSame(2, $report['summary']['contract_count']);
        $this->assertSame(1, $report['summary']['overdue_contract_count']);
        $this->assertSame(1, $report['summary']['posted_invoice_count']);
        $this->assertSame(50000, $report['summary']['rent_billed_minor']);
        $this->assertSame(7000, $report['summary']['tax_billed_minor']);
        $this->assertSame(57000, $report['summary']['total_billed_minor']);
        $this->assertSame(7500, $report['summary']['pending_damage_minor']);
        $this->assertFalse($report['readiness']['has_mixed_currency']);
        $this->assertTrue($report['readiness']['has_overdue_contracts']);
        $this->assertTrue($report['readiness']['has_pending_damage']);
    }

    public function test_rental_operations_report_routes_require_financial_visibility_and_export_permission(): void
    {
        $this->withoutVite();

        $reportsOnly = User::factory()->create();
        $reportsOnly->givePermissionTo(['reports.view']);

        $this->actingAs($reportsOnly)
            ->get('/reports/rentals')
            ->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['reports.view', 'view_financials']);

        $this->actingAs($viewer)
            ->get('/reports/rentals?as_of_date=2026-02-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/RentalOperationsReport')
                ->has('reportData.rows')
                ->has('branches')
                ->has('customers')
                ->has('currencies'));

        $this->actingAs($viewer)
            ->get('/reports/rentals/export?as_of_date=2026-02-20')
            ->assertForbidden();

        $this->actingAs($this->user);
        $response = $this->get('/reports/rentals/export?as_of_date=2026-02-20');
        $response->assertOk();
        $this->assertStringContainsString('RENTAL OPERATIONS REPORT', $response->streamedContent());
    }

    public function test_rental_reports_are_registered_without_tenant_scope_or_hardcoded_arabic_text(): void
    {
        $this->assertTrue(Schema::hasTable('rental_invoice'));
        $this->assertFalse(Schema::hasColumn('rental_invoice', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_invoice', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('rental_invoice_line', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_invoice_line', 'tenant_id'));

        $tsx = file_get_contents(resource_path('js/Pages/Reports/RentalOperationsReport.tsx'));
        $service = file_get_contents(app_path('Application/Reports/RentalOperationsReportService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reports/RentalOperationsReportController.php'));

        $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $tsx);
        $this->assertDoesNotMatchRegularExpression('/company_id|tenant_id|currentCompany|currentBranch|Spatie Teams/', $service.$controller.$tsx);
    }

    private function activeContract(): RentalContract
    {
        $service = app(RentalContractService::class);
        $contract = $this->createRentalContract();
        $approved = $service->approve($service->submit($contract->id, $this->user->id)->id, $this->user->id);

        return $service->activate($approved->id, $this->user->id);
    }

    private function postRentInvoice(RentalContract $contract)
    {
        $vat14 = TaxCode::query()->where('code', 'VAT_STD_14')->firstOrFail();
        $invoiceService = app(RentalInvoiceService::class);
        $invoice = $invoiceService->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'periodic_rent',
            'invoice_date' => '2026-01-20',
            'due_date' => '2026-01-31',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'rent',
                'rental_contract_line_id' => $contract->fresh('lines')->lines->first()->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 50000,
                'tax_code_id' => $vat14->id,
            ]],
        ], $this->user->id);

        return $invoiceService->post(
            $invoiceService->approve($invoiceService->submit($invoice->id, $this->user->id)->id, $this->user->id)->id,
            $this->user->id
        );
    }

    private function completedReturn(RentalContract $contract, int $damageMinor)
    {
        $fulfillment = app(RentalFulfillmentService::class);
        $line = $contract->fresh('lines')->lines->first();
        $return = $fulfillment->createReturn([
            'rental_contract_id' => $contract->id,
            'return_date' => '2026-02-12',
            'lines' => [[
                'rental_contract_line_id' => $line->id,
                'condition_in' => 'damaged',
                'outcome' => 'damaged',
                'estimated_damage_charge_minor' => $damageMinor,
            ]],
        ], $this->user->id);

        return $fulfillment->completeReturn($fulfillment->submitReturn($return->id, $this->user->id)->id, $this->user->id);
    }

    private function createRentalContract(): RentalContract
    {
        $item = $this->createRentableItem();
        $customer = $this->createCustomer();

        return app(RentalContractService::class)->create([
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'contract_date' => '2026-01-05',
            'start_date' => '2026-01-10',
            'expected_end_date' => '2026-02-10',
            'currency' => 'EGP',
            'billing_cycle' => 'monthly',
            'reference' => 'RENT-REP-REF',
            'notes' => 'Rental report fixture.',
            'lines' => [[
                'rentable_item_id' => $item->id,
                'description' => ['en' => 'Rental report line', 'ar' => 'بند تقرير إيجار'],
                'start_date' => '2026-01-10',
                'end_date' => '2026-02-10',
                'rate_type' => 'monthly',
                'rate_minor' => 50000,
                'estimated_units' => 1,
                'deposit_minor' => 10000,
            ]],
        ], $this->user->id);
    }

    private function createRentableItem(): RentableItem
    {
        $suffix = str_pad((string) $this->sequence++, 3, '0', STR_PAD_LEFT);

        return app(RentableItemService::class)->create([
            'code' => "RENT-REP-{$suffix}",
            'name' => ['en' => "Rental Report Item {$suffix}", 'ar' => "عنصر تقرير إيجار {$suffix}"],
            'description' => ['en' => 'Standalone rentable item', 'ar' => 'عنصر إيجار مستقل'],
            'item_source' => 'standalone',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'available',
            'condition_status' => 'good',
            'currency' => 'EGP',
            'serial_number' => "RENT-REP-SN-{$suffix}",
            'replacement_value_minor' => 500000,
            'daily_rate_minor' => 25000,
            'monthly_rate_minor' => 50000,
            'deposit_minor' => 10000,
            'is_active' => true,
        ], $this->user->id);
    }

    private function createCustomer(): Customer
    {
        $suffix = str_pad((string) $this->sequence++, 3, '0', STR_PAD_LEFT);

        return Customer::query()->create([
            'code' => "CUST-RENT-REP-{$suffix}",
            'name' => ['en' => "Rental Report Customer {$suffix}", 'ar' => "عميل تقرير إيجار {$suffix}"],
            'status' => 'active',
            'lock_version' => 1,
        ]);
    }
}
