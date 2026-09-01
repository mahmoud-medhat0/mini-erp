<?php

namespace Tests\Feature;

use App\Application\Reports\ApAgingReportService;
use App\Application\Reports\ArAgingReportService;
use App\Models\Customer;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\PayableEntrySettlement;
use App\Models\ReceivableEntry;
use App\Models\ReceivableEntrySettlement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AgingReportDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $reportUser;

    private User $unauthorizedUser;

    private FinancialPeriod $period;

    private JournalEntry $journalEntry;

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
        $this->journalEntry = JournalEntry::query()->create([
            'number' => 'JE-AGING-2026',
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-01',
            'source_type' => 'manual',
            'currency' => 'EGP',
            'status' => 'posted',
        ]);
    }

    public function test_ar_and_ap_endpoints_paginate_search_and_filter_grouped_rows(): void
    {
        $customer = $this->seedReceivableGroups();
        $supplier = $this->seedPayableGroups();

        $arPage = $this->actingAs($this->reportUser)->getJson(
            '/reports/ar-aging/data?'.http_build_query($this->dataTableParameters('customer')),
        );
        $arPage
            ->assertOk()
            ->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonCount(10, 'data');

        $arSearch = $this->actingAs($this->reportUser)->getJson(
            '/reports/ar-aging/data?'.http_build_query($this->dataTableParameters('customer', 'SEARCH-TARGET')),
        );
        $arSearch
            ->assertOk()
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.customer_code', 'CUST-SEARCH-TARGET')
            ->assertJsonPath('data.0.open_items_count', 5)
            ->assertJsonPath('data.0.current', 1000)
            ->assertJsonPath('data.0.b1_30', 2000)
            ->assertJsonPath('data.0.b31_60', 3000)
            ->assertJsonPath('data.0.b61_90', 4000)
            ->assertJsonPath('data.0.over_90', 5000)
            ->assertJsonPath('data.0.total', 15000);

        $arFilter = $this->actingAs($this->reportUser)->getJson(
            '/reports/ar-aging/data?'.http_build_query([
                ...$this->dataTableParameters('customer'),
                'customer_id' => $customer->id,
            ]),
        );
        $arFilter
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.customer_id', $customer->id);

        $apPage = $this->actingAs($this->reportUser)->getJson(
            '/reports/ap-aging/data?'.http_build_query($this->dataTableParameters('supplier')),
        );
        $apPage
            ->assertOk()
            ->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonCount(10, 'data');

        $apSearch = $this->actingAs($this->reportUser)->getJson(
            '/reports/ap-aging/data?'.http_build_query($this->dataTableParameters('supplier', 'SEARCH-TARGET')),
        );
        $apSearch
            ->assertOk()
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.supplier_code', 'SUPP-SEARCH-TARGET')
            ->assertJsonPath('data.0.open_items_count', 5)
            ->assertJsonPath('data.0.current', 6000)
            ->assertJsonPath('data.0.b1_30', 7000)
            ->assertJsonPath('data.0.b31_60', 8000)
            ->assertJsonPath('data.0.b61_90', 9000)
            ->assertJsonPath('data.0.over_90', 10000)
            ->assertJsonPath('data.0.total', 40000);

        $apFilter = $this->actingAs($this->reportUser)->getJson(
            '/reports/ap-aging/data?'.http_build_query([
                ...$this->dataTableParameters('supplier'),
                'supplier_id' => $supplier->id,
            ]),
        );
        $apFilter
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.supplier_id', $supplier->id);
    }

    public function test_aging_index_keeps_filtered_summary_without_loading_entry_rows(): void
    {
        $customer = $this->seedReceivableGroups();
        $supplier = $this->seedPayableGroups();

        $this->receivable($customer, 99999, '2026-06-10', '2026-01-01', 'USD');
        $this->receivable($customer, 88888, '2026-07-10', '2026-07-01');
        $this->payable($supplier, 99999, '2026-06-10', '2026-01-01', 'USD');
        $this->payable($supplier, 88888, '2026-07-10', '2026-07-01');

        $this->actingAs($this->reportUser)
            ->get('/reports/ar-aging?'.http_build_query([
                'as_of_date' => '2026-06-30',
                'customer_id' => $customer->id,
                'currency' => 'egp',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/ArAging')
                ->where('report.currency', 'EGP')
                ->where('report.grand_totals.current', 1000)
                ->where('report.grand_totals.b1_30', 2000)
                ->where('report.grand_totals.b31_60', 3000)
                ->where('report.grand_totals.b61_90', 4000)
                ->where('report.grand_totals.over_90', 5000)
                ->where('report.grand_totals.total', 15000)
                ->missing('report.customers'));

        $this->actingAs($this->reportUser)
            ->get('/reports/ap-aging?'.http_build_query([
                'as_of_date' => '2026-06-30',
                'supplier_id' => $supplier->id,
                'currency' => 'egp',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/ApAging')
                ->where('report.currency', 'EGP')
                ->where('report.grand_totals.current', 6000)
                ->where('report.grand_totals.b1_30', 7000)
                ->where('report.grand_totals.b31_60', 8000)
                ->where('report.grand_totals.b61_90', 9000)
                ->where('report.grand_totals.over_90', 10000)
                ->where('report.grand_totals.total', 40000)
                ->missing('report.suppliers'));
    }

    public function test_aging_data_endpoints_are_protected_and_validate_datatable_input(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson('/reports/ar-aging/data')
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->getJson('/reports/ap-aging/data')
            ->assertForbidden();

        $this->actingAs($this->reportUser)
            ->getJson('/reports/ar-aging/data?'.http_build_query([
                'as_of_date' => '31-06-2026',
                'customer_id' => 'not-a-uuid',
                'currency' => 'ZZZ',
                'length' => 500,
                'columns' => [['data' => 'supplier_name', 'name' => 'supplier_name']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['as_of_date', 'customer_id', 'currency', 'length', 'columns.0.data', 'columns.0.name']);

        $this->actingAs($this->reportUser)
            ->getJson('/reports/ap-aging/data?'.http_build_query([
                'supplier_id' => 'not-a-uuid',
                'order' => [['column' => 99, 'dir' => 'sideways']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id', 'order.0.column', 'order.0.dir']);

        $this->actingAs($this->reportUser)
            ->getJson('/reports/ar-aging/data?'.http_build_query([
                'columns' => [['data' => 'customer_name', 'name' => 'customer_name']],
                'order' => [['column' => 7, 'dir' => 'asc']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order.0.column']);
    }

    public function test_export_generators_keep_detail_contract_with_constant_query_count(): void
    {
        $customer = $this->customer('CUST-QUERY-COUNT', 'Customer Query Count');
        $supplier = $this->supplier('SUPP-QUERY-COUNT', 'Supplier Query Count');

        foreach (range(1, 20) as $index) {
            $this->receivable($customer, 1000 + $index, '2026-06-15');
            $this->payable($supplier, 2000 + $index, '2026-06-15');
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $arReport = app(ArAgingReportService::class)->generate('2026-06-30', $customer->id, 'EGP');
        $arQueryCount = count(DB::getQueryLog());

        DB::flushQueryLog();
        $apReport = app(ApAgingReportService::class)->generate('2026-06-30', $supplier->id, 'EGP');
        $apQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(20, $arReport['customers'][0]['items']);
        $this->assertCount(20, $apReport['suppliers'][0]['items']);
        $this->assertLessThanOrEqual(3, $arQueryCount, 'AR aging must not execute per-entry allocation queries.');
        $this->assertLessThanOrEqual(3, $apQueryCount, 'AP aging must not execute per-entry allocation queries.');
    }

    public function test_reversal_after_report_date_preserves_historical_ar_and_ap_balances(): void
    {
        $customer = $this->customer('CUST-HISTORICAL', 'Historical Customer');
        $receivableTarget = $this->receivable($customer, 1000, '2026-06-20');
        $receivableSource = $this->receivable($customer, 400, '2026-06-01', positive: false);
        ReceivableEntrySettlement::query()->create([
            'customer_id' => $customer->id,
            'source_receivable_entry_id' => $receivableSource->id,
            'target_receivable_entry_id' => $receivableTarget->id,
            'currency' => 'EGP',
            'amount_minor' => 400,
            'status' => 'reversed',
            'settled_at' => '2026-06-15 10:00:00',
            'reversed_at' => '2026-07-10 10:00:00',
        ]);

        $supplier = $this->supplier('SUPP-HISTORICAL', 'Historical Supplier');
        $payableTarget = $this->payable($supplier, 2000, '2026-06-20');
        $payableSource = $this->payable($supplier, 750, '2026-06-01', positive: false);
        PayableEntrySettlement::query()->create([
            'supplier_id' => $supplier->id,
            'source_payable_entry_id' => $payableSource->id,
            'target_payable_entry_id' => $payableTarget->id,
            'currency' => 'EGP',
            'amount_minor' => 750,
            'status' => 'reversed',
            'settled_at' => '2026-06-15 10:00:00',
            'reversed_at' => '2026-07-10 10:00:00',
        ]);

        $arHistorical = $this->actingAs($this->reportUser)->getJson(
            '/reports/ar-aging/data?'.http_build_query([
                ...$this->dataTableParameters('customer', 'HISTORICAL'),
                'as_of_date' => '2026-06-30',
            ]),
        );
        $arHistorical->assertOk()->assertJsonPath('data.0.total', 600);

        $apHistorical = $this->actingAs($this->reportUser)->getJson(
            '/reports/ap-aging/data?'.http_build_query([
                ...$this->dataTableParameters('supplier', 'HISTORICAL'),
                'as_of_date' => '2026-06-30',
            ]),
        );
        $apHistorical->assertOk()->assertJsonPath('data.0.total', 1250);

        $this->assertSame(600, app(ArAgingReportService::class)->generate('2026-06-30', $customer->id, 'EGP')['grand_totals']['total']);
        $this->assertSame(1250, app(ApAgingReportService::class)->generate('2026-06-30', $supplier->id, 'EGP')['grand_totals']['total']);
        $this->assertSame(1000, app(ArAgingReportService::class)->generate('2026-07-31', $customer->id, 'EGP')['grand_totals']['total']);
        $this->assertSame(2000, app(ApAgingReportService::class)->generate('2026-07-31', $supplier->id, 'EGP')['grand_totals']['total']);
    }

    private function seedReceivableGroups(): Customer
    {
        $customer = $this->customer('CUST-SEARCH-TARGET', 'Receivable Search Target');
        $target = $this->receivable($customer, 1250, '2026-07-05');
        $source = $this->receivable($customer, 250, '2026-06-01', positive: false);
        ReceivableEntrySettlement::query()->create([
            'customer_id' => $customer->id,
            'source_receivable_entry_id' => $source->id,
            'target_receivable_entry_id' => $target->id,
            'currency' => 'EGP',
            'amount_minor' => 250,
            'status' => 'active',
            'settled_at' => '2026-06-20 12:00:00',
        ]);
        $this->receivable($customer, 2000, '2026-06-15');
        $this->receivable($customer, 3000, '2026-05-15');
        $this->receivable($customer, 4000, '2026-04-15');
        $this->receivable($customer, 5000, '2026-03-01');

        foreach (range(1, 11) as $index) {
            $other = $this->customer(sprintf('CUST-PAGE-%03d', $index), "Customer Page {$index}");
            $this->receivable($other, 100, '2026-07-01');
        }

        return $customer;
    }

    private function seedPayableGroups(): Supplier
    {
        $supplier = $this->supplier('SUPP-SEARCH-TARGET', 'Payable Search Target');
        $target = $this->payable($supplier, 6250, '2026-07-05');
        $source = $this->payable($supplier, 250, '2026-06-01', positive: false);
        PayableEntrySettlement::query()->create([
            'supplier_id' => $supplier->id,
            'source_payable_entry_id' => $source->id,
            'target_payable_entry_id' => $target->id,
            'currency' => 'EGP',
            'amount_minor' => 250,
            'status' => 'active',
            'settled_at' => '2026-06-20 12:00:00',
        ]);
        $this->payable($supplier, 7000, '2026-06-15');
        $this->payable($supplier, 8000, '2026-05-15');
        $this->payable($supplier, 9000, '2026-04-15');
        $this->payable($supplier, 10000, '2026-03-01');

        foreach (range(1, 11) as $index) {
            $other = $this->supplier(sprintf('SUPP-PAGE-%03d', $index), "Supplier Page {$index}");
            $this->payable($other, 100, '2026-07-01');
        }

        return $supplier;
    }

    /** @return array<string, mixed> */
    private function dataTableParameters(string $partner, string $search = ''): array
    {
        $columns = [
            "{$partner}_name",
            'open_items_count',
            'current',
            'b1_30',
            'b31_60',
            'b61_90',
            'over_90',
            'total',
        ];

        return [
            'draw' => 4,
            'start' => 0,
            'length' => 10,
            'as_of_date' => '2026-06-30',
            'currency' => 'EGP',
            'search' => ['value' => $search],
            'columns' => array_map(fn (string $column): array => [
                'data' => $column,
                'name' => $column,
                'searchable' => $column === "{$partner}_name",
                'orderable' => true,
            ], $columns),
            'order' => [['column' => 7, 'dir' => 'desc']],
        ];
    }

    private function customer(string $code, string $name): Customer
    {
        return Customer::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'status' => 'active',
        ]);
    }

    private function supplier(string $code, string $name): Supplier
    {
        return Supplier::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'status' => 'active',
        ]);
    }

    private function receivable(
        Customer $customer,
        int $amount,
        string $dueDate,
        string $entryDate = '2026-01-01',
        string $currency = 'EGP',
        bool $positive = true,
    ): ReceivableEntry {
        return ReceivableEntry::query()->create([
            'customer_id' => $customer->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'aging_test',
            'source_id' => (string) Str::uuid(),
            'entry_date' => $entryDate,
            'due_date' => $dueDate,
            'currency' => $currency,
            'debit_minor' => $positive ? $amount : 0,
            'credit_minor' => $positive ? 0 : $amount,
        ]);
    }

    private function payable(
        Supplier $supplier,
        int $amount,
        string $dueDate,
        string $entryDate = '2026-01-01',
        string $currency = 'EGP',
        bool $positive = true,
    ): PayableEntry {
        return PayableEntry::query()->create([
            'supplier_id' => $supplier->id,
            'journal_entry_id' => $this->journalEntry->id,
            'financial_period_id' => $this->period->id,
            'source_type' => 'aging_test',
            'source_id' => (string) Str::uuid(),
            'entry_date' => $entryDate,
            'due_date' => $dueDate,
            'currency' => $currency,
            'debit_minor' => $positive ? 0 : $amount,
            'credit_minor' => $positive ? $amount : 0,
        ]);
    }
}
