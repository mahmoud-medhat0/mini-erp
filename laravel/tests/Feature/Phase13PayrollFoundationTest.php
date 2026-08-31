<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Application\Payroll\EmployeePayrollComponentService;
use App\Application\Payroll\EmployeeService;
use App\Application\Payroll\PayrollRunService;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\PayrollComponent;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase13PayrollFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'payroll.view',
            'payroll.create',
            'payroll.edit',
            'payroll.submit',
            'payroll.approve',
            'payroll.post',
            'view_payroll',
            'view_financials',
        ]);
        $this->actingAs($this->user);

        $this->branch = Branch::query()->firstOrCreate(
            ['code' => 'PAY-BR'],
            ['name' => ['en' => 'Payroll Branch', 'ar' => 'فرع المرتبات'], 'is_active' => true]
        );
    }

    public function test_phase13_schema_preserves_single_erp_scope_with_operational_branch_only(): void
    {
        foreach (['employee', 'payroll_component', 'employee_payroll_component', 'payroll_period', 'payroll_run', 'payroll_run_line', 'payroll_run_line_component'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");
            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must not contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must not contain tenant_id.");
        }

        $this->assertFalse(Schema::hasColumn('employee', 'user_id'));
        $this->assertTrue(Schema::hasColumn('employee', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('payroll_run', 'branch_id'));
        $this->assertTrue(Schema::hasColumn('payroll_run_line', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('payroll_component', 'branch_id'));
        $this->assertFalse(config('permission.teams'));
    }

    public function test_payroll_seeders_register_default_accounts_mappings_and_components(): void
    {
        foreach (['5700', '2600', '2610'] as $code) {
            $this->assertTrue(Account::query()->where('code', $code)->exists(), "Account [{$code}] must exist.");
        }

        foreach (['payroll_expense', 'payroll_payable', 'payroll_deductions_payable'] as $key) {
            $this->assertTrue(AccountingAccountMapping::query()->where('key', $key)->whereNull('branch_id')->exists(), "Mapping [{$key}] must exist.");
        }

        $this->assertGreaterThanOrEqual(5, PayrollComponent::query()->where('is_system', true)->count());
    }

    public function test_payroll_run_generation_calculates_gross_deductions_and_net_without_floats(): void
    {
        $employee = $this->createEmployee();
        $this->assignComponents($employee);

        $run = $this->createPayrollRun();

        $this->assertSame(1, $run->employee_count);
        $this->assertSame(120000, $run->gross_minor);
        $this->assertSame(10000, $run->deductions_minor);
        $this->assertSame(110000, $run->net_minor);
        $this->assertCount(1, $run->lines);
        $this->assertSame(3, $run->lines->first()->components->count());
    }

    public function test_approved_payroll_run_posts_balanced_journal_and_ledger(): void
    {
        $employee = $this->createEmployee();
        $this->assignComponents($employee);

        $service = app(PayrollRunService::class);
        $run = $this->createPayrollRun();
        $service->submit($run->id, $this->user->id);
        $approved = $service->approve($run->id, $this->user->id);
        $posted = $service->post($approved->id, $this->user->id);
        $journal = JournalEntry::query()->with('lines.account')->whereKey($posted->journal_entry_id)->firstOrFail();

        $this->assertSame('posted', $posted->status);
        $this->assertStringStartsWith('PAY-2026-', (string) $posted->number);
        $this->assertSame('payroll_run', $journal->source_type);
        $this->assertSame($posted->id, $journal->source_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame(120000, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(120000, (int) $journal->lines->sum('credit_minor'));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '5700' && (int) $line->debit_minor === 120000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '2600' && (int) $line->credit_minor === 110000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '2610' && (int) $line->credit_minor === 10000));
        $this->assertSame(3, LedgerEntry::query()->where('journal_entry_id', $journal->id)->count());
    }

    public function test_period_close_readiness_blocks_unposted_payroll_run(): void
    {
        $employee = $this->createEmployee();
        $this->assignComponents($employee);
        $run = $this->createPayrollRun();
        $period = FinancialPeriod::query()
            ->where('start_date', '<=', '2026-01-31')
            ->where('end_date', '>=', '2026-01-31')
            ->firstOrFail();

        $readiness = app(PeriodService::class)->checkCloseReadiness($period);

        $this->assertFalse($readiness['can_close']);
        $this->assertTrue(collect($readiness['blockers'])->contains(fn (array $blocker): bool => $blocker['entity_type'] === 'payroll_run' && $blocker['id'] === $run->id && $blocker['reason_code'] === 'unposted_payroll_run'));
    }

    public function test_payroll_pages_require_sensitive_view_permission_and_render_for_allowed_user(): void
    {
        $this->withoutVite();

        $limited = User::factory()->create();
        $limited->givePermissionTo('payroll.view');

        $this->actingAs($limited)->get('/payroll/runs')->assertForbidden();

        $this->actingAs($this->user);
        $this->get('/payroll/employees')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payroll/Employees')
                ->has('employees')
                ->has('branches')
                ->has('currencies')
                ->has('components'));

        $this->get('/payroll/components')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payroll/Components')
                ->has('components')
                ->has('expenseAccounts')
                ->has('liabilityAccounts'));

        $this->get('/payroll/runs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payroll/Runs')
                ->has('runs')
                ->has('periods')
                ->has('branches')
                ->has('currencies'));
    }

    private function createEmployee(array $overrides = []): Employee
    {
        return app(EmployeeService::class)->create([
            'code' => 'EMP-001',
            'name' => ['en' => 'Payroll Employee', 'ar' => 'موظف مرتبات'],
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'hire_date' => '2025-01-01',
            'termination_date' => null,
            'currency' => 'EGP',
            'base_salary_minor' => 100000,
            'payment_method' => 'bank',
            ...$overrides,
        ], $this->user->id);
    }

    private function assignComponents(Employee $employee): void
    {
        $assignmentService = app(EmployeePayrollComponentService::class);
        $allowance = PayrollComponent::query()->where('code', 'TRANSPORT_ALLOWANCE')->firstOrFail();
        $deduction = PayrollComponent::query()->where('code', 'PERCENT_DEDUCTION')->firstOrFail();

        $assignmentService->create($employee->id, [
            'payroll_component_id' => $allowance->id,
            'amount_minor' => 20000,
            'effective_from' => '2026-01-01',
        ], $this->user->id);

        $assignmentService->create($employee->id, [
            'payroll_component_id' => $deduction->id,
            'rate_bps' => 1000,
            'effective_from' => '2026-01-01',
        ], $this->user->id);
    }

    private function createPayrollRun(array $overrides = []): PayrollRun
    {
        return app(PayrollRunService::class)->createRun([
            'year' => 2026,
            'month' => 1,
            'payment_date' => '2026-01-31',
            'branch_id' => $this->branch->id,
            'run_type' => 'regular',
            'currency' => 'EGP',
            'reference' => 'PAYROLL-JAN',
            ...$overrides,
        ], $this->user->id);
    }
}
