<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccrualEntry;
use App\Models\AccrualSchedule;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PrepaidRecognition;
use App\Models\PrepaidSchedule;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePayrollOperationalDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private FinancialPeriod $financialPeriod;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class, RbacSeeder::class]);
        $this->withoutVite();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo(['expenses.view', 'payroll.view', 'view_payroll']);

        $this->branch = Branch::query()->create([
            'code' => 'DT-BR',
            'name' => ['en' => 'DataTable Branch', 'ar' => 'فرع جدول البيانات'],
            'is_active' => true,
        ]);

        $fiscalYear = FiscalYear::query()->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->financialPeriod = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        $prepaidAssetAccount = $this->createAccount('DT-1800', 'Prepaid Asset', 'asset', 'debit');
        $this->expenseAccount = $this->createAccount('DT-5100', 'Expense Account', 'expense', 'debit');
        $liabilityAccount = $this->createAccount('DT-2500', 'Accrued Liability', 'liability', 'credit');

        $category = ExpenseCategory::query()->create([
            'code' => 'DT-GENERAL',
            'name' => ['en' => 'DataTable General', 'ar' => 'مصروفات عامة'],
            'default_expense_account_id' => $this->expenseAccount->id,
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'DT-SUP',
            'name' => ['en' => 'DataTable Supplier', 'ar' => 'مورد جدول البيانات'],
            'status' => 'active',
        ]);

        Expense::query()->create([
            'number' => 'EXP-DT-001',
            'expense_date' => '2026-09-01',
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'settlement_method' => 'payable',
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $this->financialPeriod->id,
            'currency' => 'EGP',
            'subtotal_minor' => 12500,
            'tax_amount_minor' => 0,
            'total_minor' => 12500,
            'status' => 'draft',
            'reference' => 'EXPENSE-SEARCH',
        ]);

        $prepaid = PrepaidSchedule::query()->create([
            'number' => 'PRE-DT-001',
            'schedule_date' => '2026-09-02',
            'start_date' => '2026-09-01',
            'months' => 1,
            'branch_id' => $this->branch->id,
            'expense_category_id' => $category->id,
            'prepaid_asset_account_id' => $prepaidAssetAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $this->financialPeriod->id,
            'currency' => 'EGP',
            'total_minor' => 12000,
            'status' => 'draft',
            'reference' => 'PREPAID-SEARCH',
        ]);

        PrepaidRecognition::query()->create([
            'prepaid_schedule_id' => $prepaid->id,
            'financial_period_id' => $this->financialPeriod->id,
            'recognition_date' => '2026-09-30',
            'amount_minor' => 12000,
            'status' => 'pending',
        ]);

        $accrual = AccrualSchedule::query()->create([
            'number' => 'ACC-DT-001',
            'schedule_date' => '2026-09-03',
            'start_date' => '2026-09-01',
            'months' => 1,
            'branch_id' => $this->branch->id,
            'expense_category_id' => $category->id,
            'expense_account_id' => $this->expenseAccount->id,
            'accrued_liability_account_id' => $liabilityAccount->id,
            'fiscal_year_id' => $fiscalYear->id,
            'financial_period_id' => $this->financialPeriod->id,
            'currency' => 'EGP',
            'total_minor' => 9000,
            'status' => 'draft',
            'reference' => 'ACCRUAL-SEARCH',
        ]);

        AccrualEntry::query()->create([
            'accrual_schedule_id' => $accrual->id,
            'financial_period_id' => $this->financialPeriod->id,
            'accrual_date' => '2026-09-30',
            'amount_minor' => 9000,
            'status' => 'pending',
        ]);

        Employee::query()->create([
            'code' => 'EMP-DT-001',
            'name' => ['en' => 'DataTable Employee', 'ar' => 'موظف جدول البيانات'],
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'hire_date' => '2025-01-01',
            'currency' => 'EGP',
            'base_salary_minor' => 100000,
            'payment_method' => 'bank',
        ]);

        $payrollPeriod = PayrollPeriod::query()->create([
            'year' => 2026,
            'month' => 9,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'payment_date' => '2026-09-30',
            'financial_period_id' => $this->financialPeriod->id,
            'status' => 'open',
        ]);

        PayrollRun::query()->create([
            'number' => 'PAY-DT-001',
            'payroll_period_id' => $payrollPeriod->id,
            'branch_id' => $this->branch->id,
            'financial_period_id' => $this->financialPeriod->id,
            'payroll_date' => '2026-09-30',
            'run_type' => 'regular',
            'currency' => 'EGP',
            'status' => 'draft',
            'employee_count' => 3,
            'gross_minor' => 300000,
            'deductions_minor' => 30000,
            'net_minor' => 270000,
            'reference' => 'PAYROLL-SEARCH',
        ]);
    }

    public function test_index_payloads_are_slim_and_metrics_use_full_register_summaries(): void
    {
        $this->actingAs($this->user)
            ->get('/expenses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Index')
                ->where('expenses', [])
                ->where('summary.total_minor', 12500)
                ->where('summary.currency', 'EGP')
                ->where('summary.posted_count', 0)
                ->where('summary.pipeline_count', 1));

        $this->actingAs($this->user)
            ->get('/expenses/prepaids')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Prepaids')
                ->where('schedules', [])
                ->where('summary.total_schedules', 1)
                ->where('summary.pending_recognitions', 1));

        $this->actingAs($this->user)
            ->get('/expenses/accruals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Accruals')
                ->where('schedules', [])
                ->where('summary.total_schedules', 1)
                ->where('summary.pending_entries', 1));

        $this->actingAs($this->user)
            ->get('/payroll/employees')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Employees')
                ->where('employees', []));

        $this->actingAs($this->user)
            ->get('/payroll/runs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Runs')
                ->where('runs', [])
                ->where('summary.total_runs', 1)
                ->where('summary.employee_count', 3)
                ->where('summary.gross_minor', 300000)
                ->where('summary.net_minor', 270000));
    }

    public function test_datatables_search_filter_and_return_action_relations_server_side(): void
    {
        $cases = [
            [
                '/expenses/data',
                $this->expenseColumns(),
                'EXPENSE-SEARCH',
                ['status' => 'draft', 'branch_id' => $this->branch->id],
                'number',
                'EXP-DT-001',
                'data.0.supplier.code',
                'DT-SUP',
                'cancelled',
            ],
            [
                '/expenses/prepaids/data',
                $this->scheduleColumns('recognitions'),
                'PREPAID-SEARCH',
                ['status' => 'draft', 'branch_id' => $this->branch->id],
                'number',
                'PRE-DT-001',
                'data.0.recognitions.0.status',
                'pending',
                'cancelled',
            ],
            [
                '/expenses/accruals/data',
                $this->scheduleColumns('entries'),
                'ACCRUAL-SEARCH',
                ['status' => 'draft', 'branch_id' => $this->branch->id],
                'number',
                'ACC-DT-001',
                'data.0.entries.0.status',
                'pending',
                'cancelled',
            ],
            [
                '/payroll/employees/data',
                $this->employeeColumns(),
                'DataTable Employee',
                ['status' => 'active', 'branch_id' => $this->branch->id],
                'code',
                'EMP-DT-001',
                'data.0.branch.code',
                'DT-BR',
                'terminated',
            ],
            [
                '/payroll/runs/data',
                $this->payrollRunColumns(),
                'PAYROLL-SEARCH',
                ['status' => 'draft', 'branch_id' => $this->branch->id],
                'number',
                'PAY-DT-001',
                'data.0.period.year',
                2026,
                'cancelled',
            ],
        ];

        foreach ($cases as [$uri, $columns, $search, $filters, $key, $expected, $relationPath, $relationValue, $mismatchStatus]) {
            $query = array_merge($this->gridQuery($columns, $search), $filters);
            $response = $this->actingAs($this->user)->getJson($uri.'?'.http_build_query($query));

            $response->assertOk();
            $this->assertSame(1, $response->json('recordsFiltered'), "{$uri} did not return its matching row: ".$response->getContent());
            $response->assertJsonPath("data.0.{$key}", $expected);
            $response->assertJsonPath($relationPath, $relationValue);

            $mismatchQuery = array_merge($this->gridQuery($columns, $search), $filters, ['status' => $mismatchStatus]);
            $mismatch = $this->actingAs($this->user)->getJson($uri.'?'.http_build_query($mismatchQuery));
            $mismatch->assertOk();
            $this->assertSame(0, $mismatch->json('recordsFiltered'), "{$uri} ignored its status filter: ".$mismatch->getContent());
        }
    }

    public function test_datatable_ordering_is_applied_by_the_database(): void
    {
        Expense::query()->create([
            'number' => 'EXP-DT-002',
            'expense_date' => '2026-09-20',
            'branch_id' => $this->branch->id,
            'supplier_id' => Supplier::query()->where('code', 'DT-SUP')->value('id'),
            'settlement_method' => 'payable',
            'fiscal_year_id' => $this->financialPeriod->fiscal_year_id,
            'financial_period_id' => $this->financialPeriod->id,
            'currency' => 'EGP',
            'subtotal_minor' => 5000,
            'tax_amount_minor' => 0,
            'total_minor' => 5000,
            'status' => 'draft',
        ]);

        $query = array_merge($this->gridQuery($this->expenseColumns(), '', 1, 'desc'), [
            'status' => 'draft',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/expenses/data?'.http_build_query($query));

        $response->assertOk()->assertJsonPath('data.0.number', 'EXP-DT-002');
        $this->assertSame(2, $response->json('recordsFiltered'));
    }

    public function test_datatable_routes_preserve_their_view_permissions(): void
    {
        $stranger = User::factory()->create();

        foreach ([
            ['/expenses/data', $this->expenseColumns()],
            ['/expenses/prepaids/data', $this->scheduleColumns('recognitions')],
            ['/expenses/accruals/data', $this->scheduleColumns('entries')],
            ['/payroll/employees/data', $this->employeeColumns()],
            ['/payroll/runs/data', $this->payrollRunColumns()],
        ] as [$uri, $columns]) {
            $this->actingAs($stranger)
                ->getJson($uri.'?'.http_build_query($this->gridQuery($columns, '')))
                ->assertForbidden();
        }
    }

    private function createAccount(string $code, string $name, string $type, string $nature): Account
    {
        return Account::query()->create([
            'code' => $code,
            'name' => ['en' => $name, 'ar' => $name],
            'type' => $type,
            'nature' => $nature,
            'currency' => 'EGP',
            'is_control' => false,
            'allow_manual_posting' => true,
            'is_active' => true,
        ]);
    }

    /** @return array<int, array{string, bool, bool}> */
    private function expenseColumns(): array
    {
        return [
            ['number', true, true],
            ['expense_date', true, true],
            ['settlement_method', true, true],
            ['branch_name', false, false],
            ['payee', false, false],
            ['total_minor', false, true],
            ['status', true, true],
            ['actions', false, false],
        ];
    }

    /** @return array<int, array{string, bool, bool}> */
    private function scheduleColumns(string $detailColumn): array
    {
        return [
            ['number', true, true],
            ['schedule_date', true, true],
            ['start_date', true, true],
            ['months', false, true],
            ['branch_name', false, false],
            ['total_minor', false, true],
            ['status', true, true],
            [$detailColumn, false, false],
            ['actions', false, false],
        ];
    }

    /** @return array<int, array{string, bool, bool}> */
    private function employeeColumns(): array
    {
        return [
            ['code', true, true],
            ['employee_name', false, false],
            ['branch_name', false, false],
            ['base_salary_minor', false, true],
            ['status', true, true],
            ['actions', false, false],
        ];
    }

    /** @return array<int, array{string, bool, bool}> */
    private function payrollRunColumns(): array
    {
        return [
            ['number', true, true],
            ['period_label', false, false],
            ['branch_name', false, false],
            ['gross_minor', false, true],
            ['net_minor', false, true],
            ['status', true, true],
            ['actions', false, false],
        ];
    }

    /**
     * @param  array<int, array{string, bool, bool}>  $columns
     * @return array<string, mixed>
     */
    private function gridQuery(array $columns, string $search, int $orderColumn = 1, string $direction = 'desc'): array
    {
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
            'order' => [['column' => (string) $orderColumn, 'dir' => $direction]],
            'search' => ['value' => $search, 'regex' => 'false'],
        ];
    }
}
