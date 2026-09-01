<?php

namespace Tests\Feature;

use App\Application\Purchasing\SupplierBillService;
use App\Application\Reports\VatRegisterDataTableService;
use App\Application\Sales\CustomerCreditNoteService;
use App\Application\Sales\CustomerInvoiceService;
use App\Application\Taxes\TaxMasterDataService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VatRegisterDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $financialUser;

    private User $unauthorizedUser;

    private Customer $customer;

    private Supplier $supplier;

    private Product $product;

    private TaxCode $vat14;

    private string $currency = 'USD';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        Permission::findOrCreate('reports.view', 'web');
        Permission::findOrCreate('reports.export', 'web');
        Permission::findOrCreate('view_financials', 'web');

        $this->financialUser = User::factory()->create();
        $this->financialUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials']);
        $this->unauthorizedUser = User::factory()->create();

        $taxMasterService = app(TaxMasterDataService::class);
        $this->vat14 = $taxMasterService->createTaxCode([
            'code' => 'VAT_DT_14',
            'name' => ['en' => 'DataTable VAT 14%', 'ar' => 'ضريبة 14%'],
            'calculation_mode' => 'exclusive',
            'recoverability_mode' => 'full',
        ]);
        $taxMasterService->createTaxRate([
            'tax_code_id' => $this->vat14->id,
            'rate_bps' => 1400,
            'effective_from' => '2020-01-01',
        ]);

        $this->customer = Customer::query()->first()
            ?? Customer::query()->create(['code' => 'CUST-DT-1', 'name' => 'DataTable Customer', 'status' => 'active']);
        $this->supplier = Supplier::query()->first()
            ?? Supplier::query()->create(['code' => 'SUPP-DT-1', 'name' => 'DataTable Supplier', 'status' => 'active']);

        $uom = UnitOfMeasure::query()->first()
            ?? UnitOfMeasure::query()->create(['code' => 'PCS', 'name' => ['en' => 'Pieces', 'ar' => 'قطع']]);
        $this->product = Product::query()->create([
            'code' => 'PROD-DT-1',
            'name' => ['en' => 'DataTable Product', 'ar' => 'منتج'],
            'type' => 'service',
            'status' => 'active',
            'is_sales_enabled' => true,
            'is_purchase_enabled' => true,
            'unit_of_measure_id' => $uom->id,
        ]);

        Account::query()->update(['currency' => $this->currency]);

        $outputAccount = Account::query()->where('code', '2200')->first();
        if ($outputAccount) {
            AccountingAccountMapping::query()->updateOrCreate(
                ['key' => 'output_tax_payable'],
                ['account_id' => $outputAccount->id]
            );
        }

        $inputAccount = Account::query()->where('code', '1300')->first();
        if ($inputAccount) {
            AccountingAccountMapping::query()->updateOrCreate(
                ['key' => 'input_tax_receivable'],
                ['account_id' => $inputAccount->id]
            );
        }
    }

    public function test_endpoint_unions_output_and_input_documents_with_signed_amounts(): void
    {
        $this->postSalesInvoice('2026-08-15', 10000);
        $this->postSupplierBill('2026-08-15', 20000);

        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl());

        $response->assertOk()
            ->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 2)
            ->assertJsonCount(2, 'data');

        $byCategory = [];
        foreach ($response->json('data') as $row) {
            $byCategory[$row['tax_category']] = $row;
        }

        $this->assertArrayHasKey('output', $byCategory);
        $this->assertArrayHasKey('input', $byCategory);
        $this->assertSame(1400, $byCategory['output']['tax_amount_minor']);
        $this->assertSame(10000, $byCategory['output']['subtotal_minor']);
        $this->assertSame('customer_invoice', $byCategory['output']['document_type']);
        $this->assertSame(2800, $byCategory['input']['tax_amount_minor']);
        $this->assertSame(20000, $byCategory['input']['subtotal_minor']);
        $this->assertSame('supplier_bill', $byCategory['input']['document_type']);
    }

    public function test_credit_note_rows_are_negative_and_summary_matches_datatable_rows(): void
    {
        $invoice = $this->postSalesInvoice('2026-08-15', 10000);
        $this->postCreditNote($invoice, '2026-08-15', 4000);
        $this->postSupplierBill('2026-08-15', 20000);

        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl());

        $response->assertOk()->assertJsonPath('recordsTotal', 3);

        $creditRow = null;
        foreach ($response->json('data') as $row) {
            if ($row['document_type'] === 'customer_credit_note') {
                $creditRow = $row;
            }
        }

        $this->assertNotNull($creditRow, 'Credit note row must appear in the VAT register.');
        $this->assertSame(-560, $creditRow['tax_amount_minor']);
        $this->assertSame(-4000, $creditRow['subtotal_minor']);

        $summary = app(VatRegisterDataTableService::class)->summary([
            'from_date' => '2026-08-15',
            'to_date' => '2026-08-15',
            'type' => 'all',
            'tax_code_id' => null,
        ]);

        $this->assertSame(840, $summary['summary']['total_output_tax_minor']);
        $this->assertSame(2800, $summary['summary']['total_input_tax_minor']);
        $this->assertSame(-1960, $summary['summary']['net_vat_payable_minor']);
    }

    public function test_type_filter_restricts_rows_to_the_requested_tax_category(): void
    {
        $this->postSalesInvoice('2026-08-15', 10000);
        $this->postSupplierBill('2026-08-15', 20000);

        $outputResponse = $this->actingAs($this->financialUser)
            ->getJson($this->dataTableUrl(['type' => 'output']));

        $outputResponse->assertOk()->assertJsonPath('recordsTotal', 1);
        $this->assertSame('output', $outputResponse->json('data.0.tax_category'));

        $inputResponse = $this->actingAs($this->financialUser)
            ->getJson($this->dataTableUrl(['type' => 'input']));

        $inputResponse->assertOk()->assertJsonPath('recordsTotal', 1);
        $this->assertSame('input', $inputResponse->json('data.0.tax_category'));
    }

    public function test_date_range_filter_excludes_documents_outside_the_period(): void
    {
        $this->postSalesInvoice('2026-08-15', 10000);
        $this->postSalesInvoice('2026-09-15', 30000);

        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl([
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.subtotal_minor', 10000);
    }

    public function test_search_is_case_insensitive_across_document_and_entity_columns(): void
    {
        $invoice = $this->postSalesInvoice('2026-08-15', 10000);
        $this->postSupplierBill('2026-08-15', 20000);

        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl(
            [],
            mb_strtolower($invoice->number),
        ));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_number', $invoice->number);
    }

    public function test_server_side_pagination_limits_the_returned_rows(): void
    {
        foreach (range(1, 6) as $index) {
            $this->postSalesInvoice('2026-08-15', 1000 * $index);
        }

        $response = $this->actingAs($this->financialUser)
            ->getJson($this->dataTableUrl([], '', 4, 10));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 6)
            ->assertJsonPath('recordsFiltered', 6)
            ->assertJsonCount(2, 'data');
    }

    public function test_endpoint_enforces_permissions_and_rejects_malformed_datatables_payload(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson('/reports/vat-register/data?from_date=2026-08-01&to_date=2026-08-31')
            ->assertForbidden();

        $payload = $this->dataTablePayload();
        $payload['type'] = 'sideways';
        $payload['tax_code_id'] = 'not-a-uuid';
        $payload['from_date'] = '2026-09-01';
        $payload['to_date'] = '2026-08-01';
        $payload['length'] = 999;
        $payload['search']['value'] = str_repeat('x', 151);
        $payload['columns'][0]['name'] = 'document_date; DROP TABLE customer_invoice';
        $payload['order'][0]['column'] = 99;

        $this->actingAs($this->financialUser)
            ->getJson('/reports/vat-register/data?'.http_build_query($payload))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'tax_code_id',
                'to_date',
                'length',
                'search.value',
                'columns.0.name',
                'order.0.column',
            ]);
    }

    public function test_csv_export_remains_complete_and_is_not_limited_to_a_datatable_page(): void
    {
        foreach (range(1, 12) as $index) {
            $this->postSalesInvoice('2026-08-15', 1000 + $index);
        }

        $response = $this->actingAs($this->financialUser)
            ->get('/reports/vat-register/export?from_date=2026-08-01&to_date=2026-08-31');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertSame(12, substr_count($csv, 'customer_invoice'));
    }

    private function postSalesInvoice(string $date, int $unitPriceMinor): object
    {
        $service = app(CustomerInvoiceService::class);
        $invoice = $service->create([
            'customer_id' => $this->customer->id,
            'invoice_date' => $date,
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Sales Item',
                    'quantity_e6' => 1000000,
                    'unit_price_minor' => $unitPriceMinor,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->financialUser->id);
        $service->approve($invoice->id, $this->financialUser->id);

        return $service->post($invoice->id, $this->financialUser->id);
    }

    private function postCreditNote(object $invoice, string $date, int $unitPriceMinor): void
    {
        $service = app(CustomerCreditNoteService::class);
        $creditNote = $service->create([
            'customer_id' => $this->customer->id,
            'customer_invoice_id' => $invoice->id,
            'credit_date' => $date,
            'currency' => $this->currency,
            'lines' => [
                [
                    'customer_invoice_line_id' => $invoice->lines->first()->id,
                    'description' => 'Return Partial',
                    'unit_price_minor' => $unitPriceMinor,
                ],
            ],
        ], $this->financialUser->id);
        $service->approve($creditNote->id, $this->financialUser->id);
        $service->post($creditNote->id, $this->financialUser->id);
    }

    private function postSupplierBill(string $date, int $unitCostMinor): void
    {
        $service = app(SupplierBillService::class);
        $bill = $service->create([
            'supplier_id' => $this->supplier->id,
            'bill_date' => $date,
            'currency' => $this->currency,
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'unit_of_measure_id' => $this->product->unit_of_measure_id,
                    'description' => 'Purchase Item',
                    'quantity_e6' => 1000000,
                    'unit_cost_minor' => $unitCostMinor,
                    'tax_code_id' => $this->vat14->id,
                ],
            ],
        ], $this->financialUser->id);
        $service->submit($bill->id, $this->financialUser->id);
        $service->approve($bill->id, $this->financialUser->id);
        $service->post($bill->id, $this->financialUser->id);
    }

    /** @param array<string, string> $filters */
    private function dataTableUrl(
        array $filters = [],
        string $search = '',
        int $start = 0,
        int $length = 25,
    ): string {
        return '/reports/vat-register/data?'.http_build_query([
            ...$this->dataTablePayload($search, $start, $length),
            ...$filters,
        ]);
    }

    /** @return array<string, mixed> */
    private function dataTablePayload(string $search = '', int $start = 0, int $length = 25): array
    {
        $columns = [
            'document_date',
            'document_type',
            'document_number',
            'entity_name',
            'tax_category',
            'tax_code',
            'subtotal_minor',
            'tax_amount_minor',
            'gross_amount_minor',
        ];

        return [
            'from_date' => '2026-08-01',
            'to_date' => '2026-09-30',
            'type' => 'all',
            'draw' => 7,
            'start' => $start,
            'length' => $length,
            'search' => ['value' => $search, 'regex' => 'false'],
            'columns' => array_map(static fn (string $column): array => [
                'data' => $column,
                'name' => $column,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => 0, 'dir' => 'asc']],
        ];
    }
}
