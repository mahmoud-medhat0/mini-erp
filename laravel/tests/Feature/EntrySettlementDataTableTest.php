<?php

namespace Tests\Feature;

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
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntrySettlementDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['sales.credit_notes', 'purchasing.adjustment_notes']);
        $this->customer = Customer::query()->create([
            'code' => 'CUST-SET-DT',
            'name' => ['en' => 'Settlement Customer', 'ar' => 'عميل التسوية'],
            'status' => 'active',
        ]);
        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-SET-DT',
            'name' => ['en' => 'Settlement Supplier', 'ar' => 'مورد التسوية'],
            'status' => 'active',
        ]);

        $year = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
        $period = FinancialPeriod::query()->create([
            'fiscal_year_id' => $year->id,
            'month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
        $journal = JournalEntry::query()->create([
            'number' => 'JE-SET-DT',
            'financial_period_id' => $period->id,
            'entry_date' => '2026-09-01',
            'source_type' => 'manual',
            'currency' => 'EGP',
            'status' => 'posted',
        ]);

        $receivableSource = $this->receivable($journal, $period, debit: 0, credit: 5000);
        $receivableTarget = $this->receivable($journal, $period, debit: 5000, credit: 0);
        ReceivableEntrySettlement::query()->create([
            'customer_id' => $this->customer->id,
            'source_receivable_entry_id' => $receivableSource->id,
            'target_receivable_entry_id' => $receivableTarget->id,
            'currency' => 'EGP',
            'amount_minor' => 5000,
            'status' => 'active',
            'settled_at' => '2026-09-05 10:00:00',
            'created_by' => $this->user->id,
        ]);

        $payableSource = $this->payable($journal, $period, debit: 7000, credit: 0);
        $payableTarget = $this->payable($journal, $period, debit: 0, credit: 7000);
        PayableEntrySettlement::query()->create([
            'supplier_id' => $this->supplier->id,
            'source_payable_entry_id' => $payableSource->id,
            'target_payable_entry_id' => $payableTarget->id,
            'currency' => 'EGP',
            'amount_minor' => 7000,
            'status' => 'active',
            'settled_at' => '2026-09-06 10:00:00',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_index_payloads_do_not_ship_settlement_history(): void
    {
        $this->actingAs($this->user)
            ->get('/sales/receivable-settlements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('existingSettlements', []));

        $this->actingAs($this->user)
            ->get('/purchasing/payable-settlements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('existingSettlements', []));
    }

    public function test_receivable_and_payable_histories_are_server_filtered(): void
    {
        $this->actingAs($this->user)
            ->getJson('/sales/receivable-settlements/data?'.http_build_query(
                $this->gridQuery('customer', 'receivable', 'CUST-SET-DT', ['customer_id' => $this->customer->id]),
            ))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.amount_minor', 5000)
            ->assertJsonPath('data.0.customer.code', 'CUST-SET-DT');

        $this->actingAs($this->user)
            ->getJson('/purchasing/payable-settlements/data?'.http_build_query(
                $this->gridQuery('supplier', 'payable', 'SUPP-SET-DT', ['supplier_id' => $this->supplier->id]),
            ))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.amount_minor', 7000)
            ->assertJsonPath('data.0.supplier.code', 'SUPP-SET-DT');
    }

    public function test_settlement_tables_reuse_existing_permissions(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/sales/receivable-settlements/data?'.http_build_query($this->gridQuery('customer', 'receivable')))
            ->assertForbidden();
        $this->actingAs($stranger)
            ->getJson('/purchasing/payable-settlements/data?'.http_build_query($this->gridQuery('supplier', 'payable')))
            ->assertForbidden();
    }

    private function receivable(JournalEntry $journal, FinancialPeriod $period, int $debit, int $credit): ReceivableEntry
    {
        return ReceivableEntry::query()->create([
            'customer_id' => $this->customer->id,
            'journal_entry_id' => $journal->id,
            'financial_period_id' => $period->id,
            'source_type' => 'datatable_test',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'currency' => 'EGP',
            'debit_minor' => $debit,
            'credit_minor' => $credit,
        ]);
    }

    private function payable(JournalEntry $journal, FinancialPeriod $period, int $debit, int $credit): PayableEntry
    {
        return PayableEntry::query()->create([
            'supplier_id' => $this->supplier->id,
            'journal_entry_id' => $journal->id,
            'financial_period_id' => $period->id,
            'source_type' => 'datatable_test',
            'source_id' => (string) Str::uuid(),
            'entry_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'currency' => 'EGP',
            'debit_minor' => $debit,
            'credit_minor' => $credit,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function gridQuery(string $partner, string $entry, string $search = '', array $extra = []): array
    {
        $columns = [
            'settled_at',
            "{$partner}_name",
            "source_{$entry}_entry_id",
            "target_{$entry}_entry_id",
            'amount_minor',
            'status',
            'actions',
        ];

        return array_merge([
            'draw' => '1',
            'start' => '0',
            'length' => '25',
            'columns' => array_map(fn (string $column): array => [
                'data' => $column,
                'name' => $column,
                'searchable' => $column !== 'actions' ? 'true' : 'false',
                'orderable' => $column !== 'actions' ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => '0', 'dir' => 'desc']],
            'search' => ['value' => $search, 'regex' => 'false'],
        ], $extra);
    }
}
