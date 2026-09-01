<?php

namespace Tests\Feature;

use App\Application\Reports\CustomerStatementReportService;
use App\Application\Reports\SupplierStatementReportService;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\ReceivableEntry;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PartnerStatementDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $reportUser;

    private User $unauthorizedUser;

    private FinancialPeriod $period;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reports.view', 'reports.export', 'view_financials'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->reportUser = User::factory()->create();
        $this->reportUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials']);
        $this->unauthorizedUser = User::factory()->create();

        $year = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $this->period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $year->id,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $this->customer = Customer::query()->create([
            'code' => 'CUST-STMT-001',
            'name' => ['en' => 'Statement Customer', 'ar' => 'Statement Customer'],
            'status' => 'active',
        ]);
        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-STMT-001',
            'name' => ['en' => 'Statement Supplier', 'ar' => 'Statement Supplier'],
            'status' => 'active',
        ]);
    }

    public function test_customer_statement_paginates_and_preserves_running_balance_during_search(): void
    {
        $this->seedCustomerStatement();

        $firstPage = $this->actingAs($this->reportUser)->getJson(
            '/reports/customer-statement/data?'.http_build_query($this->dataTableParameters('customer', orderDirection: 'desc')),
        );
        $firstPage
            ->assertOk()
            ->assertJsonPath('draw', 7)
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.date', '2026-01-12')
            ->assertJsonPath('data.0.running_balance_minor', 2050)
            ->assertJsonPath('data.9.date', '2026-01-03')
            ->assertJsonPath('data.9.running_balance_minor', 1300);

        $secondPage = $this->actingAs($this->reportUser)->getJson(
            '/reports/customer-statement/data?'.http_build_query($this->dataTableParameters('customer', start: 10)),
        );
        $secondPage
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-01-11')
            ->assertJsonPath('data.0.running_balance_minor', 1950)
            ->assertJsonPath('data.1.running_balance_minor', 2050);

        foreach ([
            'CUSTOMER INVOICE' => 12,
            'ar-mixedcase-needle' => 1,
            'needle description' => 1,
            '2026-01-07' => 1,
        ] as $search => $expectedCount) {
            $response = $this->actingAs($this->reportUser)->getJson(
                '/reports/customer-statement/data?'.http_build_query($this->dataTableParameters('customer', search: $search)),
            );
            $response
                ->assertOk()
                ->assertJsonPath('recordsFiltered', $expectedCount);

            if ($expectedCount === 1) {
                $response
                    ->assertJsonPath('data.0.reference', 'AR-MixedCase-Needle')
                    ->assertJsonPath('data.0.running_balance_minor', 1550);
            }
        }
    }

    public function test_supplier_statement_paginates_and_preserves_running_balance_during_search(): void
    {
        $this->seedSupplierStatement();

        $page = $this->actingAs($this->reportUser)->getJson(
            '/reports/supplier-statement/data?'.http_build_query($this->dataTableParameters('supplier', start: 10)),
        );
        $page
            ->assertOk()
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-01-11')
            ->assertJsonPath('data.0.running_balance_minor', 3920)
            ->assertJsonPath('data.1.running_balance_minor', 4120);

        $typeSearch = $this->actingAs($this->reportUser)->getJson(
            '/reports/supplier-statement/data?'.http_build_query($this->dataTableParameters('supplier', search: 'SUPPLIER BILL')),
        );
        $typeSearch
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 12)
            ->assertJsonPath('data.0.type', 'Supplier Bill');

        $referenceSearch = $this->actingAs($this->reportUser)->getJson(
            '/reports/supplier-statement/data?'.http_build_query($this->dataTableParameters('supplier', search: 'ap-mixedcase-needle')),
        );
        $referenceSearch
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.reference', 'AP-MixedCase-Needle')
            ->assertJsonPath('data.0.running_balance_minor', 3120);
    }

    public function test_statement_pages_return_summary_only_and_exports_keep_full_generate_contract(): void
    {
        $this->seedCustomerStatement();
        $this->seedSupplierStatement();

        $this->actingAs($this->reportUser)
            ->get('/reports/customer-statement?'.http_build_query([
                'customer_id' => $this->customer->id,
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
                'currency' => 'egp',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/CustomerStatement')
                ->where('report.opening_balance_minor', 1000)
                ->where('report.total_debit_minor', 1100)
                ->where('report.total_credit_minor', 50)
                ->where('report.closing_balance_minor', 2050)
                ->where('report.filters.currency', 'EGP')
                ->missing('report.lines'));

        $this->actingAs($this->reportUser)
            ->get('/reports/supplier-statement?'.http_build_query([
                'supplier_id' => $this->supplier->id,
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
                'currency' => 'egp',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/SupplierStatement')
                ->where('report.opening_balance_minor', 2000)
                ->where('report.total_debit_minor', 80)
                ->where('report.total_credit_minor', 2200)
                ->where('report.closing_balance_minor', 4120)
                ->where('report.filters.currency', 'EGP')
                ->missing('report.lines'));

        $customerExport = app(CustomerStatementReportService::class)->generate(
            $this->customer->id,
            '2026-01-01',
            '2026-01-31',
            'EGP',
        );
        $supplierExport = app(SupplierStatementReportService::class)->generate(
            $this->supplier->id,
            '2026-01-01',
            '2026-01-31',
            'EGP',
        );

        $this->assertCount(12, $customerExport['lines']);
        $this->assertSame(2050, $customerExport['closing_balance_minor']);
        $this->assertCount(12, $supplierExport['lines']);
        $this->assertSame(4120, $supplierExport['closing_balance_minor']);
    }

    public function test_statement_data_endpoints_are_protected_and_validate_payloads(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson('/reports/customer-statement/data')
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->getJson('/reports/supplier-statement/data')
            ->assertForbidden();

        $this->actingAs($this->reportUser)
            ->getJson('/reports/customer-statement/data?'.http_build_query([
                'customer_id' => 'not-a-uuid',
                'supplier_id' => $this->supplier->id,
                'date_from' => '31-01-2026',
                'date_to' => '2025-12-31',
                'currency' => 'ZZZ',
                'length' => 500,
                'columns' => [['data' => 'unsafe_column', 'name' => 'unsafe_column']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
                'supplier_id',
                'date_from',
                'date_to',
                'currency',
                'length',
                'columns.0.data',
                'columns.0.name',
            ]);

        $malformedOrder = $this->dataTableParameters('customer');
        $malformedOrder['columns'] = [[
            'data' => 'date',
            'name' => 'date',
            'searchable' => 'sometimes',
            'orderable' => true,
            'search' => ['value' => '', 'regex' => true],
        ]];
        $malformedOrder['search']['regex'] = true;
        $malformedOrder['order'] = [['column' => 6, 'dir' => 'asc']];

        $this->actingAs($this->reportUser)
            ->getJson('/reports/customer-statement/data?'.http_build_query($malformedOrder))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'search.regex',
                'columns.0.searchable',
                'columns.0.search.regex',
                'order.0.column',
            ]);

        $this->actingAs($this->reportUser)
            ->getJson('/reports/supplier-statement/data?'.http_build_query([
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
                'currency' => 'EGP',
                'order' => [['column' => 99, 'dir' => 'sideways']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id', 'order.0.column', 'order.0.dir']);
    }

    public function test_statement_query_count_is_constant_as_lines_grow(): void
    {
        $this->seedCustomerStatement();
        $this->seedSupplierStatement();

        $customerBefore = $this->statementEndpointQueryCount('customer');
        $supplierBefore = $this->statementEndpointQueryCount('supplier');

        foreach (range(13, 32) as $index) {
            $date = sprintf('2026-01-%02d', (($index - 1) % 28) + 1);
            $this->receivable($index, $date, 100, 0, "AR-EXTRA-{$index}", "Extra customer movement {$index}");
            $this->payable($index, $date, 0, 200, "AP-EXTRA-{$index}", "Extra supplier movement {$index}");
        }

        $customerAfter = $this->statementEndpointQueryCount('customer');
        $supplierAfter = $this->statementEndpointQueryCount('supplier');

        $this->assertGreaterThan(0, $customerBefore);
        $this->assertGreaterThan(0, $supplierBefore);
        $this->assertSame($customerBefore, $customerAfter, 'Customer statement must not query per movement.');
        $this->assertSame($supplierBefore, $supplierAfter, 'Supplier statement must not query per movement.');

        DB::enableQueryLog();
        DB::flushQueryLog();
        app(CustomerStatementReportService::class)->generate($this->customer->id, '2026-01-01', '2026-01-31', 'EGP');
        $customerExportQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        app(SupplierStatementReportService::class)->generate($this->supplier->id, '2026-01-01', '2026-01-31', 'EGP');
        $supplierExportQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $customerExportQueries);
        $this->assertLessThanOrEqual(7, $supplierExportQueries);
    }

    private function seedCustomerStatement(): void
    {
        $this->receivable(0, '2025-12-31', 1000, 0, 'AR-OPENING', 'Prior customer balance');

        foreach (range(1, 12) as $index) {
            $reference = $index === 7 ? 'AR-MixedCase-Needle' : sprintf('AR-REF-%03d', $index);
            $description = $index === 7 ? 'Needle Description' : "Customer movement {$index}";
            $this->receivable(
                $index,
                sprintf('2026-01-%02d', $index),
                $index === 5 ? 0 : 100,
                $index === 5 ? 50 : 0,
                $reference,
                $description,
            );
        }

        $this->receivable(99, '2026-01-15', 99999, 0, 'AR-USD', 'Wrong currency', 'USD');
    }

    private function seedSupplierStatement(): void
    {
        $this->payable(0, '2025-12-31', 0, 2000, 'AP-OPENING', 'Prior supplier balance');

        foreach (range(1, 12) as $index) {
            $reference = $index === 7 ? 'AP-MixedCase-Needle' : sprintf('AP-REF-%03d', $index);
            $description = $index === 7 ? 'Needle Description' : "Supplier movement {$index}";
            $this->payable(
                $index,
                sprintf('2026-01-%02d', $index),
                $index === 5 ? 80 : 0,
                $index === 5 ? 0 : 200,
                $reference,
                $description,
            );
        }

        $this->payable(99, '2026-01-15', 0, 99999, 'AP-USD', 'Wrong currency', 'USD');
    }

    /** @return array<string, mixed> */
    private function dataTableParameters(
        string $partner,
        string $search = '',
        int $start = 0,
        string $orderDirection = 'asc',
    ): array {
        $columns = ['date', 'type', 'reference', 'description', 'debit_minor', 'credit_minor', 'running_balance_minor'];

        return [
            $partner.'_id' => $partner === 'customer' ? $this->customer->id : $this->supplier->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'currency' => 'EGP',
            'draw' => 7,
            'start' => $start,
            'length' => 10,
            'search' => ['value' => $search, 'regex' => false],
            'columns' => array_map(fn (string $column): array => [
                'data' => $column,
                'name' => $column,
                'searchable' => in_array($column, ['date', 'type', 'reference', 'description'], true),
                'orderable' => true,
                'search' => ['value' => '', 'regex' => false],
            ], $columns),
            'order' => [['column' => 0, 'dir' => $orderDirection]],
        ];
    }

    private function statementEndpointQueryCount(string $partner): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($this->reportUser)->getJson(
            "/reports/{$partner}-statement/data?".http_build_query($this->dataTableParameters($partner)),
        )->assertOk();

        $table = $partner === 'customer' ? 'receivable_entry' : 'payable_entry';
        $count = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), $table))
            ->count();

        DB::disableQueryLog();

        return $count;
    }

    private function receivable(
        int $sequence,
        string $date,
        int $debit,
        int $credit,
        string $reference,
        string $description,
        string $currency = 'EGP',
    ): ReceivableEntry {
        $journal = $this->journal("JE-AR-{$sequence}-".Str::random(6), $date, $reference, $currency);

        return ReceivableEntry::query()->create([
            'customer_id' => $this->customer->id,
            'journal_entry_id' => $journal->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'customer_invoice',
            'source_id' => (string) Str::uuid(),
            'entry_date' => $date,
            'description' => $description,
            'currency' => $currency,
            'debit_minor' => $debit,
            'credit_minor' => $credit,
        ]);
    }

    private function payable(
        int $sequence,
        string $date,
        int $debit,
        int $credit,
        string $reference,
        string $description,
        string $currency = 'EGP',
    ): PayableEntry {
        $journal = $this->journal("JE-AP-{$sequence}-".Str::random(6), $date, $reference, $currency);

        return PayableEntry::query()->create([
            'supplier_id' => $this->supplier->id,
            'journal_entry_id' => $journal->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'supplier_bill',
            'source_id' => (string) Str::uuid(),
            'entry_date' => $date,
            'description' => $description,
            'currency' => $currency,
            'debit_minor' => $debit,
            'credit_minor' => $credit,
        ]);
    }

    private function journal(string $number, string $date, string $reference, string $currency): JournalEntry
    {
        return JournalEntry::query()->create([
            'number' => $number,
            'financial_period_id' => $this->period->id,
            'entry_date' => $date,
            'source_type' => 'manual',
            'reference' => $reference,
            'currency' => $currency,
            'status' => 'posted',
        ]);
    }
}
