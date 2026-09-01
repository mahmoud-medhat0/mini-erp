<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\IncomingCheque;
use App\Models\OutgoingCheque;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChequeRegisterDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $financialUser;

    private User $unauthorizedUser;

    private Customer $customer;

    private Supplier $supplier;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('reports.view', 'web');
        Permission::findOrCreate('reports.export', 'web');
        Permission::findOrCreate('view_financials', 'web');

        $this->financialUser = User::factory()->create();
        $this->financialUser->givePermissionTo(['reports.view', 'reports.export', 'view_financials']);
        $this->unauthorizedUser = User::factory()->create();

        $glAccount = Account::query()->create([
            'code' => 'CHEQUE-DT-GL',
            'name' => ['en' => 'Cheque bank GL', 'ar' => 'حساب بنك الشيكات'],
            'type' => 'asset',
            'nature' => 'debit',
            'is_active' => true,
        ]);
        $this->bankAccount = BankAccount::query()->create([
            'code' => 'CHEQUE-BANK',
            'name' => ['en' => 'Cheque Bank', 'ar' => 'بنك الشيكات'],
            'bank_name' => ['en' => 'National Bank', 'ar' => 'البنك الوطني'],
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);
        $this->customer = Customer::query()->create([
            'code' => 'CUST-ALPHA',
            'name' => ['en' => 'Alpha Customer', 'ar' => 'عميل ألفا'],
            'currency' => 'EGP',
            'status' => 'active',
        ]);
        $this->supplier = Supplier::query()->create([
            'code' => 'SUP-BETA',
            'name' => ['en' => 'Beta Supplier', 'ar' => 'مورد بيتا'],
            'currency' => 'EGP',
            'status' => 'active',
        ]);
    }

    public function test_endpoint_unions_both_directions_and_searches_case_insensitively(): void
    {
        $incoming = $this->incomingCheque('IN-Needle-001', '2026-01-05', 12500, 'received');
        $this->outgoingCheque('OUT-OTHER-001', '2026-01-06', 8000, 'issued');

        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl(
            ['direction' => 'all'],
            'nEeDlE',
        ));

        $response->assertOk()
            ->assertJsonPath('draw', 9)
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $incoming->id)
            ->assertJsonPath('data.0.direction', 'incoming')
            ->assertJsonPath('data.0.cheque_number', 'IN-Needle-001')
            ->assertJsonPath('data.0.party_code', 'CUST-ALPHA')
            ->assertJsonPath('data.0.bank_account_code', 'CHEQUE-BANK')
            ->assertJsonPath('data.0.amount_minor', 12500)
            ->assertJsonPath('data.0.status', 'received');
    }

    public function test_filters_and_page_summary_are_sql_backed_without_items_payload(): void
    {
        $this->incomingCheque('IN-JAN-001', '2026-01-05', 10000, 'received');
        $this->incomingCheque('IN-FEB-001', '2026-02-05', 20000, 'cleared');
        $this->outgoingCheque('OUT-JAN-001', '2026-01-07', 30000, 'issued');

        $query = http_build_query([
            'direction' => 'incoming',
            'status' => 'received',
            'customer_id' => $this->customer->id,
            'bank_account_id' => $this->bankAccount->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'currency' => 'EGP',
        ]);
        $response = $this->actingAs($this->financialUser)->get('/reports/cheque-register?'.$query);

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Reports/ChequeRegister')
            ->where('report.total_count', 1)
            ->where('report.incoming_total_minor', 10000)
            ->where('report.outgoing_total_minor', 0)
            ->where('report.total_amount_minor', 10000)
            ->missing('report.items'));

        $dataResponse = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl([
            'direction' => 'incoming',
            'status' => 'received',
            'customer_id' => $this->customer->id,
            'bank_account_id' => $this->bankAccount->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]));
        $dataResponse->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.cheque_number', 'IN-JAN-001');

        $this->actingAs($this->financialUser)->getJson($this->dataTableUrl([
            'direction' => 'outgoing',
            'supplier_id' => $this->supplier->id,
            'bank_account_id' => $this->bankAccount->id,
        ]))->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.cheque_number', 'OUT-JAN-001');
    }

    public function test_pagination_uses_deterministic_due_date_and_cheque_number_order(): void
    {
        foreach (range(12, 1) as $number) {
            $this->incomingCheque(
                sprintf('IN-%03d', $number),
                '2026-01-15',
                1000 + $number,
                'received',
            );
        }

        $response = $this->actingAs($this->financialUser)->getJson($this->dataTableUrl(
            ['direction' => 'incoming'],
            '',
            10,
        ));

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 12)
            ->assertJsonPath('recordsFiltered', 12)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.cheque_number', 'IN-011')
            ->assertJsonPath('data.1.cheque_number', 'IN-012');
    }

    public function test_endpoint_enforces_permissions_and_rejects_malformed_datatables_payload(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson('/reports/cheque-register/data')
            ->assertForbidden();

        $payload = $this->dataTablePayload();
        $payload['direction'] = 'sideways';
        $payload['currency'] = 'INVALID';
        $payload['customer_id'] = 'not-a-uuid';
        $payload['date_from'] = '2026-02-01';
        $payload['date_to'] = '2026-01-01';
        $payload['length'] = 999;
        $payload['search']['value'] = str_repeat('x', 151);
        $payload['columns'][0]['name'] = 'party_name; DROP TABLE incoming_cheque';
        $payload['order'][0]['column'] = 99;

        $this->actingAs($this->financialUser)
            ->getJson('/reports/cheque-register/data?'.http_build_query($payload))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'direction',
                'currency',
                'customer_id',
                'date_to',
                'length',
                'search.value',
                'columns.0.name',
                'order.0.column',
            ]);
    }

    public function test_csv_export_remains_complete_and_is_not_limited_to_a_datatable_page(): void
    {
        foreach (range(1, 12) as $number) {
            $this->incomingCheque(
                sprintf('IN-CSV-%03d', $number),
                '2026-01-15',
                1000 + $number,
                'received',
            );
        }

        $response = $this->actingAs($this->financialUser)
            ->get('/reports/cheque-register/export?direction=incoming&currency=EGP');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('IN-CSV-001', $csv);
        $this->assertStringContainsString('IN-CSV-012', $csv);
        $this->assertSame(12, substr_count($csv, 'IN-CSV-'));
    }

    private function incomingCheque(
        string $chequeNumber,
        string $dueDate,
        int $amountMinor,
        string $status,
    ): IncomingCheque {
        return IncomingCheque::query()->create([
            'customer_id' => $this->customer->id,
            'cheque_number' => $chequeNumber,
            'drawer_bank_name' => 'Drawer Bank',
            'due_date' => $dueDate,
            'received_date' => $dueDate,
            'deposit_bank_account_id' => $this->bankAccount->id,
            'currency' => 'EGP',
            'amount_minor' => $amountMinor,
            'status' => $status,
            'reference' => 'REF-'.$chequeNumber,
            'description' => 'Incoming '.$chequeNumber,
        ]);
    }

    private function outgoingCheque(
        string $chequeNumber,
        string $dueDate,
        int $amountMinor,
        string $status,
    ): OutgoingCheque {
        return OutgoingCheque::query()->create([
            'supplier_id' => $this->supplier->id,
            'bank_account_id' => $this->bankAccount->id,
            'cheque_number' => $chequeNumber,
            'payee_name' => 'Beta Supplier',
            'due_date' => $dueDate,
            'issued_date' => $dueDate,
            'currency' => 'EGP',
            'amount_minor' => $amountMinor,
            'status' => $status,
            'reference' => 'REF-'.$chequeNumber,
            'description' => 'Outgoing '.$chequeNumber,
        ]);
    }

    /** @param array<string, string> $filters */
    private function dataTableUrl(array $filters = [], string $search = '', int $start = 0): string
    {
        return '/reports/cheque-register/data?'.http_build_query([
            ...$this->dataTablePayload($search, $start),
            ...$filters,
        ]);
    }

    /** @return array<string, mixed> */
    private function dataTablePayload(string $search = '', int $start = 0): array
    {
        $columns = ['party_name', 'cheque_number', 'due_date', 'bank_account_name', 'status', 'amount_minor'];

        return [
            'direction' => 'all',
            'currency' => 'EGP',
            'draw' => 9,
            'start' => $start,
            'length' => 10,
            'search' => ['value' => $search, 'regex' => 'false'],
            'columns' => array_map(fn (string $name): array => [
                'data' => $name,
                'name' => $name,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $columns),
            'order' => [['column' => 2, 'dir' => 'asc']],
        ];
    }
}
