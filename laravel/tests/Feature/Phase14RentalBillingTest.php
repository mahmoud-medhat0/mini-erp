<?php

namespace Tests\Feature;

use App\Application\Accounting\AccountingAccountMappingService;
use App\Application\Accounting\PeriodService;
use App\Application\Rentals\RentableItemService;
use App\Application\Rentals\RentalContractService;
use App\Application\Rentals\RentalFulfillmentService;
use App\Application\Rentals\RentalInvoiceService;
use App\Application\Reports\VatRegisterReportService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\ReceivableEntry;
use App\Models\RentableItem;
use App\Models\RentalContract;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase14RentalBillingTest extends TestCase
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
            'view_financials',
        ]);
        $this->actingAs($this->user);

        $this->branch = Branch::query()->firstOrCreate(
            ['code' => 'RENT-BILL-BR'],
            ['name' => ['en' => 'Rental Billing Branch', 'ar' => 'فرع فوترة الإيجار'], 'is_active' => true]
        );

        $this->warehouse = Warehouse::query()->create([
            'code' => 'RENT-BILL-WH',
            'name' => ['en' => 'Rental Billing Warehouse', 'ar' => 'مخزن فوترة الإيجار'],
            'branch_id' => $this->branch->id,
            'warehouse_type' => 'standard',
            'is_default' => false,
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_rental_invoice_schema_and_registry_have_no_company_or_tenant_scope(): void
    {
        $this->assertTrue(Schema::hasTable('rental_invoice'));
        $this->assertTrue(Schema::hasTable('rental_invoice_line'));
        $this->assertTrue(Schema::hasColumn('rental_invoice', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('rental_invoice', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_invoice', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('rental_invoice_line', 'company_id'));
        $this->assertFalse(Schema::hasColumn('rental_invoice_line', 'tenant_id'));

        $entity = config('erp_attachments.entities.rental_invoice');
        $this->assertSame('rental_invoice', $entity['table']);
        $this->assertSame(['rentals.view'], $entity['permissions']['view']);

        foreach (['rental_revenue', 'rental_damage_revenue', 'rental_late_fee_revenue', 'rental_other_revenue', 'rental_deposit_liability'] as $mappingKey) {
            $this->assertDatabaseHas('accounting_account_mapping', ['key' => $mappingKey, 'branch_id' => null]);
        }
    }

    public function test_rent_and_deposit_invoice_posts_to_ar_gl_and_receivable_with_tax(): void
    {
        $contract = $this->approvedContract();
        $contractLine = $contract->lines->first();
        $vat14 = TaxCode::query()->where('code', 'VAT_STD_14')->firstOrFail();
        $invoiceService = app(RentalInvoiceService::class);

        $invoice = $invoiceService->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'mixed',
            'invoice_date' => '2026-01-20',
            'due_date' => '2026-01-31',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [
                [
                    'line_type' => 'rent',
                    'rental_contract_line_id' => $contractLine->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 50000,
                    'tax_code_id' => $vat14->id,
                ],
                [
                    'line_type' => 'deposit',
                    'rental_contract_line_id' => $contractLine->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 10000,
                ],
            ],
        ], $this->user->id);

        $this->assertSame('draft', $invoice->status);
        $this->assertSame(60000, $invoice->subtotal_minor);
        $this->assertSame(7000, $invoice->tax_amount_minor);
        $this->assertSame(67000, $invoice->total_minor);

        $posted = $invoiceService->post(
            $invoiceService->approve($invoiceService->submit($invoice->id, $this->user->id)->id, $this->user->id)->id,
            $this->user->id
        );

        $this->assertSame('posted', $posted->status);
        $this->assertStringStartsWith('RINV-2026-', (string) $posted->number);
        $this->assertNotNull($posted->journal_entry_id);
        $this->assertNotNull($posted->receivable_entry_id);

        $receivable = ReceivableEntry::query()->where('source_type', 'rental_invoice')->where('source_id', $posted->id)->firstOrFail();
        $this->assertSame($posted->customer_id, $receivable->customer_id);
        $this->assertSame(67000, $receivable->debit_minor);
        $this->assertSame(0, $receivable->credit_minor);

        $mapping = app(AccountingAccountMappingService::class);
        $this->assertLedgerAmount($posted->journal_entry_id, $mapping->getAccount('ar_control', $this->branch->id)->id, 67000, 0);
        $this->assertLedgerAmount($posted->journal_entry_id, $mapping->getAccount('rental_revenue', $this->branch->id)->id, 0, 50000);
        $this->assertLedgerAmount($posted->journal_entry_id, $mapping->getAccount('rental_deposit_liability', $this->branch->id)->id, 0, 10000);
        $this->assertLedgerAmount($posted->journal_entry_id, $mapping->getAccount('output_tax_payable', $this->branch->id)->id, 0, 7000);

        $this->assertTrue(JournalEntry::query()->whereKey($posted->journal_entry_id)->where('source_type', 'rental_invoice')->where('status', 'posted')->exists());
        $this->assertDatabaseHas('activity_log', ['event' => 'rental_invoice.post']);
    }

    public function test_duplicate_rent_invoice_for_same_contract_line_and_period_is_rejected(): void
    {
        $contract = $this->approvedContract();
        $line = $contract->lines->first();
        $service = app(RentalInvoiceService::class);
        $payload = [
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'periodic_rent',
            'invoice_date' => '2026-01-20',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'rent',
                'rental_contract_line_id' => $line->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 50000,
            ]],
        ];

        $service->create($payload, $this->user->id);

        $this->expectException(ValidationException::class);
        $service->create($payload, $this->user->id);
    }

    public function test_completed_return_damage_charge_can_be_billed_once_up_to_inspected_amount(): void
    {
        $contract = $this->activeContract();
        $return = $this->completedReturn($contract, 7500);
        $returnLine = $return->lines->first();
        $service = app(RentalInvoiceService::class);

        $invoice = $service->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'final_charges',
            'invoice_date' => '2026-02-12',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'damage_charge',
                'rental_return_line_id' => $returnLine->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 7500,
            ]],
        ], $this->user->id);

        $this->assertSame(7500, $invoice->total_minor);
        $this->assertSame($returnLine->id, $invoice->lines->first()->rental_return_line_id);

        $this->expectException(ValidationException::class);
        $service->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'final_charges',
            'invoice_date' => '2026-02-13',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'damage_charge',
                'rental_return_line_id' => $returnLine->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 1,
            ]],
        ], $this->user->id);
    }

    public function test_period_close_readiness_blocks_unposted_rental_invoice_until_posted(): void
    {
        $contract = $this->approvedContract();
        $invoice = app(RentalInvoiceService::class)->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'periodic_rent',
            'invoice_date' => '2026-01-20',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'rent',
                'rental_contract_line_id' => $contract->lines->first()->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 50000,
            ]],
        ], $this->user->id);

        $readiness = app(PeriodService::class)->checkCloseReadiness($invoice->financialPeriod);
        $this->assertContains('unposted_rental_invoice', array_column($readiness['blockers'], 'reason_code'));

        $service = app(RentalInvoiceService::class);
        $posted = $service->post($service->approve($service->submit($invoice->id, $this->user->id)->id, $this->user->id)->id, $this->user->id);

        $readinessAfterPost = app(PeriodService::class)->checkCloseReadiness($posted->financialPeriod);
        $rentalBlockers = array_filter(
            $readinessAfterPost['blockers'],
            fn (array $blocker): bool => $blocker['reason_code'] === 'unposted_rental_invoice' && $blocker['id'] === $posted->id
        );

        $this->assertSame([], array_values($rentalBlockers));
    }

    public function test_rental_invoices_page_requires_permission_and_renders_references(): void
    {
        $this->withoutVite();

        $limited = User::factory()->create();
        $this->actingAs($limited)->get('/rentals/invoices')->assertForbidden();

        $this->actingAs($this->user);
        $contract = $this->approvedContract();
        app(RentalInvoiceService::class)->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'periodic_rent',
            'invoice_date' => '2026-01-20',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'rent',
                'rental_contract_line_id' => $contract->lines->first()->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 50000,
            ]],
        ], $this->user->id);

        $this->get('/rentals/invoices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rentals/Invoices')
                ->has('invoices.data', 1)
                ->has('contracts')
                ->has('taxCodes')
                ->where('lineTypes.0', 'rent'));
    }

    public function test_rental_invoice_post_requires_financial_visibility_permission(): void
    {
        $contract = $this->approvedContract();
        $service = app(RentalInvoiceService::class);
        $invoice = $service->approve($service->submit($service->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'periodic_rent',
            'invoice_date' => '2026-01-20',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'rent',
                'rental_contract_line_id' => $contract->lines->first()->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 50000,
            ]],
        ], $this->user->id)->id, $this->user->id)->id, $this->user->id);

        $limitedPoster = User::factory()->create();
        $limitedPoster->givePermissionTo(['rentals.view', 'rentals.post']);

        $this->actingAs($limitedPoster)
            ->post("/rentals/invoices/{$invoice->id}/post")
            ->assertForbidden();
    }

    public function test_vat_register_includes_posted_rental_invoice_output_tax(): void
    {
        $contract = $this->approvedContract();
        $vat14 = TaxCode::query()->where('code', 'VAT_STD_14')->firstOrFail();
        $service = app(RentalInvoiceService::class);
        $invoice = $service->create([
            'rental_contract_id' => $contract->id,
            'invoice_type' => 'periodic_rent',
            'invoice_date' => '2026-01-20',
            'billing_period_start' => '2026-01-10',
            'billing_period_end' => '2026-02-10',
            'currency' => 'EGP',
            'lines' => [[
                'line_type' => 'rent',
                'rental_contract_line_id' => $contract->lines->first()->id,
                'quantity_e6' => 1000000,
                'unit_amount_minor' => 50000,
                'tax_code_id' => $vat14->id,
            ]],
        ], $this->user->id);

        $posted = $service->post($service->approve($service->submit($invoice->id, $this->user->id)->id, $this->user->id)->id, $this->user->id);
        $report = app(VatRegisterReportService::class)->generate([
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'type' => 'output',
            'tax_code_id' => $vat14->id,
        ]);

        $rentalRows = collect($report['rows'])->where('document_type', 'rental_invoice')->where('document_id', $posted->id)->values();

        $this->assertCount(1, $rentalRows);
        $this->assertSame(50000, $rentalRows[0]['subtotal_minor']);
        $this->assertSame(7000, $rentalRows[0]['tax_amount_minor']);
    }

    private function approvedContract(): RentalContract
    {
        $service = app(RentalContractService::class);
        $contract = $this->createRentalContract();

        return $service->approve($service->submit($contract->id, $this->user->id)->id, $this->user->id);
    }

    private function activeContract(): RentalContract
    {
        $service = app(RentalContractService::class);
        $contract = $this->approvedContract();

        return $service->activate($contract->id, $this->user->id);
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
            'reference' => 'RENT-BILL-REF',
            'notes' => 'Rental billing fixture.',
            'lines' => [[
                'rentable_item_id' => $item->id,
                'description' => ['en' => 'Rental billing line', 'ar' => 'بند فوترة إيجار'],
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
            'code' => "RENT-BILL-{$suffix}",
            'name' => ['en' => "Rental Billing Item {$suffix}", 'ar' => "عنصر فوترة إيجار {$suffix}"],
            'description' => ['en' => 'Standalone rentable item', 'ar' => 'عنصر إيجار مستقل'],
            'item_source' => 'standalone',
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'available',
            'condition_status' => 'good',
            'currency' => 'EGP',
            'serial_number' => "RENT-BILL-SN-{$suffix}",
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
            'code' => "CUST-RENT-BILL-{$suffix}",
            'name' => ['en' => "Rental Billing Customer {$suffix}", 'ar' => "عميل فوترة إيجار {$suffix}"],
            'status' => 'active',
            'lock_version' => 1,
        ]);
    }

    private function assertLedgerAmount(string $journalEntryId, string $accountId, int $debitMinor, int $creditMinor): void
    {
        $this->assertTrue(
            LedgerEntry::query()
                ->where('journal_entry_id', $journalEntryId)
                ->where('account_id', $accountId)
                ->where('branch_id', $this->branch->id)
                ->where('debit_minor', $debitMinor)
                ->where('credit_minor', $creditMinor)
                ->exists(),
            "Expected ledger amount was not found for account [{$accountId}]."
        );
    }
}
