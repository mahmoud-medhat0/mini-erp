<?php

namespace Tests\Feature;

use App\Application\Payroll\EmployeeService;
use App\Application\Payroll\PayrollEmployeeLoanService;
use App\Application\Payroll\PayrollRunService;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollEmployeeLoan;
use App\Models\PayrollEmployeeLoanInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 28 - Payroll employee loans/advances. Disbursement posts a real GL
 * entry, and repayment happens automatically as a deduction injected into the
 * normal payroll run cycle (PHASE_25_GAP_CLOSURE_DECISION_PACK.md §7) - no
 * separate repayment posting code exists, so this also exercises
 * PayrollRunService's own posting path end to end.
 */
class Phase28PayrollEmployeeLoanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Employee $employee;

    private CashAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);
        $this->withoutVite();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.submit', 'payroll.approve', 'payroll.post',
            'view_payroll', 'view_financials', 'reports.view',
        ]);
        $this->actingAs($this->user);

        $this->branch = Branch::query()->firstOrCreate(
            ['code' => 'LOAN-BR'],
            ['name' => ['en' => 'Loan Branch', 'ar' => 'فرع القروض'], 'is_active' => true]
        );

        $this->employee = app(EmployeeService::class)->create([
            'code' => 'EMP-LOAN-001',
            'name' => ['en' => 'Loan Employee', 'ar' => 'موظف السلفة'],
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'hire_date' => '2025-01-01',
            'currency' => 'EGP',
            'base_salary_minor' => 500_000,
            'payment_method' => 'bank',
        ], $this->user->id);

        $cashGlAccount = Account::query()->where('code', '1100')->firstOrFail();
        $this->cashAccount = CashAccount::query()->create([
            'code' => 'LOAN-CASH-01',
            'name' => ['en' => 'Loan Test Cash', 'ar' => 'خزينة اختبار السلف'],
            'gl_account_id' => $cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'lock_version' => 1,
        ]);
    }

    public function test_disbursing_a_loan_posts_a_balanced_journal_entry(): void
    {
        $loanService = app(PayrollEmployeeLoanService::class);

        $loan = $loanService->disburse([
            'employee_id' => $this->employee->id,
            'loan_type' => 'loan',
            'currency' => 'EGP',
            'principal_minor' => 300_000,
            'installment_amount_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $this->assertSame('active', $loan->status);
        $this->assertSame(300_000, $loan->remaining_balance_minor);
        $this->assertNotNull($loan->journal_entry_id);

        $journal = JournalEntry::query()->with('lines.account')->findOrFail($loan->journal_entry_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame('payroll_employee_loan', $journal->source_type);
        $this->assertSame(300_000, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(300_000, (int) $journal->lines->sum('credit_minor'));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '1850' && (int) $line->debit_minor === 300_000));
        $this->assertNotNull($journal->lines->first(fn ($line) => $line->account?->code === '1100' && (int) $line->credit_minor === 300_000));
    }

    public function test_posting_a_payroll_run_automatically_applies_the_loan_installment_and_decrements_the_balance(): void
    {
        $loanService = app(PayrollEmployeeLoanService::class);
        $loan = $loanService->disburse([
            'employee_id' => $this->employee->id,
            'loan_type' => 'advance',
            'currency' => 'EGP',
            'principal_minor' => 150_000,
            'installment_amount_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $payrollService = app(PayrollRunService::class);
        $run = $payrollService->createRun([
            'year' => 2026,
            'month' => 1,
            'payment_date' => '2026-01-31',
            'branch_id' => $this->branch->id,
            'run_type' => 'regular',
            'currency' => 'EGP',
            'reference' => 'PAYROLL-LOAN-TEST',
        ], $this->user->id);

        $line = $run->fresh(['lines.components'])->lines->firstWhere('employee_id', $this->employee->id);
        $this->assertNotNull($line);
        $loanComponent = $line->components->firstWhere('code', 'LOAN_REPAYMENT');
        $this->assertNotNull($loanComponent, 'Loan repayment deduction must be injected automatically.');
        $this->assertSame(100_000, (int) $loanComponent->amount_minor);
        $this->assertSame($loan->id, $loanComponent->payroll_employee_loan_id);

        $payrollService->submit($run->id, $this->user->id);
        $payrollService->approve($run->id, $this->user->id);
        $posted = $payrollService->post($run->id, $this->user->id);

        $loan = $loan->fresh();
        $this->assertSame(50_000, $loan->remaining_balance_minor);
        $this->assertSame('active', $loan->status);

        $installment = PayrollEmployeeLoanInstallment::query()->where('payroll_employee_loan_id', $loan->id)->sole();
        $this->assertSame(100_000, $installment->amount_minor);
        $this->assertSame($posted->id, $installment->payroll_run_id);

        // Posting twice must never double-apply (post() itself is idempotent
        // and returns early for an already-posted run).
        $payrollService->post($run->id, $this->user->id);
        $this->assertSame(1, PayrollEmployeeLoanInstallment::query()->where('payroll_employee_loan_id', $loan->id)->count());
    }

    public function test_loan_completes_automatically_once_the_final_installment_clears_the_balance(): void
    {
        $loanService = app(PayrollEmployeeLoanService::class);
        $loanService->disburse([
            'employee_id' => $this->employee->id,
            'loan_type' => 'loan',
            'currency' => 'EGP',
            'principal_minor' => 100_000,
            'installment_amount_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $payrollService = app(PayrollRunService::class);
        $run = $payrollService->createRun([
            'year' => 2026,
            'month' => 1,
            'payment_date' => '2026-01-31',
            'branch_id' => $this->branch->id,
            'run_type' => 'regular',
            'currency' => 'EGP',
        ], $this->user->id);
        $payrollService->submit($run->id, $this->user->id);
        $payrollService->approve($run->id, $this->user->id);
        $payrollService->post($run->id, $this->user->id);

        $loan = PayrollEmployeeLoan::query()->where('employee_id', $this->employee->id)->sole();
        $this->assertSame(0, $loan->remaining_balance_minor);
        $this->assertSame('completed', $loan->status);
    }

    public function test_installment_amount_cannot_exceed_principal(): void
    {
        $this->expectException(ValidationException::class);

        app(PayrollEmployeeLoanService::class)->disburse([
            'employee_id' => $this->employee->id,
            'loan_type' => 'loan',
            'currency' => 'EGP',
            'principal_minor' => 50_000,
            'installment_amount_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);
    }

    public function test_a_loan_with_repayments_already_applied_cannot_be_cancelled(): void
    {
        $loanService = app(PayrollEmployeeLoanService::class);
        $loan = $loanService->disburse([
            'employee_id' => $this->employee->id,
            'loan_type' => 'loan',
            'currency' => 'EGP',
            'principal_minor' => 150_000,
            'installment_amount_minor' => 100_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        $payrollService = app(PayrollRunService::class);
        $run = $payrollService->createRun([
            'year' => 2026, 'month' => 1, 'payment_date' => '2026-01-31',
            'branch_id' => $this->branch->id, 'run_type' => 'regular', 'currency' => 'EGP',
        ], $this->user->id);
        $payrollService->submit($run->id, $this->user->id);
        $payrollService->approve($run->id, $this->user->id);
        $payrollService->post($run->id, $this->user->id);

        $this->expectException(ValidationException::class);
        $loanService->cancel($loan->id, $this->user->id);
    }

    public function test_loan_routes_require_payroll_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('dashboard.view');
        $this->actingAs($viewer)->get('/payroll/loans')->assertForbidden();

        $this->actingAs($this->user)->get('/payroll/loans')->assertOk();

        $this->actingAs($this->user)->post('/payroll/loans', [
            'employee_id' => $this->employee->id,
            'loan_type' => 'loan',
            'currency' => 'EGP',
            'principal_minor' => 100_000,
            'installment_amount_minor' => 50_000,
            'disbursement_date' => '2026-01-05',
            'disbursement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payroll_employee_loan', ['employee_id' => $this->employee->id, 'status' => 'active']);
    }

    public function test_payroll_report_route_requires_view_payroll_permission(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo(['reports.view', 'view_financials']);
        $this->actingAs($limited)->get('/reports/payroll')->assertForbidden();

        $this->actingAs($this->user)->get('/reports/payroll')->assertOk();
    }
}
