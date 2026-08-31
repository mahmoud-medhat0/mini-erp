<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CostCenter;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase16Slice5BudgetFoundationTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period1;

    private FinancialPeriod $period2;

    private Account $expenseAccount1;

    private Account $expenseAccount2;

    private Project $project;

    private CostCenter $costCenter;

    private User $authorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->fiscalYear = FiscalYear::query()->firstOrCreate(
            ['year' => 2026],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'status' => 'open',
            ]
        );

        $this->period1 = FinancialPeriod::query()->firstOrCreate(
            ['fiscal_year_id' => $this->fiscalYear->id, 'month' => 1],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'status' => 'open',
            ]
        );

        $this->period2 = FinancialPeriod::query()->firstOrCreate(
            ['fiscal_year_id' => $this->fiscalYear->id, 'month' => 2],
            [
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-28',
                'status' => 'open',
            ]
        );

        $expenseAccounts = Account::query()
            ->where('type', 'expense')
            ->where('is_control', false)
            ->limit(2)
            ->get();

        if ($expenseAccounts->count() >= 2) {
            $this->expenseAccount1 = $expenseAccounts[0];
            $this->expenseAccount2 = $expenseAccounts[1];
        } else {
            $this->expenseAccount1 = Account::query()->firstOrFail();
            $this->expenseAccount2 = Account::query()->skip(1)->firstOrFail();
        }

        $this->project = Project::query()->firstOrCreate(
            ['code' => 'PRJ-ALPHA'],
            [
                'name' => ['en' => 'Project Alpha', 'ar' => 'مشروع ألفا'],
                'status' => 'active',
                'is_active' => true,
            ]
        );

        $this->costCenter = CostCenter::query()->firstOrCreate(
            ['code' => 'CC-OPS'],
            [
                'name' => ['en' => 'Operations CC', 'ar' => 'مركز عمليات'],
                'category' => 'operations',
                'is_active' => true,
            ]
        );

        $this->authorizedUser = User::factory()->create(['locale' => 'en']);
        $this->authorizedUser->givePermissionTo([
            'budgeting.view',
            'budgeting.create',
            'budgeting.edit',
            'budgeting.delete',
            'budgeting.approve',
            'budgeting.export',
            'view_financials',
        ]);
    }

    public function test_budget_and_budget_line_tables_exist_with_standalone_schema(): void
    {
        $this->assertTrue(Schema::hasTable('budget'));
        $this->assertTrue(Schema::hasTable('budget_line'));

        $budgetColumns = Schema::getColumnListing('budget');
        $budgetLineColumns = Schema::getColumnListing('budget_line');

        $this->assertContains('id', $budgetColumns);
        $this->assertContains('fiscal_year_id', $budgetColumns);
        $this->assertContains('code', $budgetColumns);
        $this->assertContains('version_code', $budgetColumns);
        $this->assertContains('name', $budgetColumns);
        $this->assertContains('description', $budgetColumns);
        $this->assertContains('status', $budgetColumns);
        $this->assertContains('default_currency', $budgetColumns);
        $this->assertContains('submitted_by', $budgetColumns);
        $this->assertContains('submitted_at', $budgetColumns);
        $this->assertContains('approved_by', $budgetColumns);
        $this->assertContains('approved_at', $budgetColumns);
        $this->assertContains('activated_by', $budgetColumns);
        $this->assertContains('activated_at', $budgetColumns);
        $this->assertContains('archived_by', $budgetColumns);
        $this->assertContains('archived_at', $budgetColumns);
        $this->assertContains('cancelled_by', $budgetColumns);
        $this->assertContains('cancelled_at', $budgetColumns);
        $this->assertContains('lock_version', $budgetColumns);

        $this->assertContains('id', $budgetLineColumns);
        $this->assertContains('budget_id', $budgetLineColumns);
        $this->assertContains('financial_period_id', $budgetLineColumns);
        $this->assertContains('account_id', $budgetLineColumns);
        $this->assertContains('project_id', $budgetLineColumns);
        $this->assertContains('cost_center_id', $budgetLineColumns);
        $this->assertContains('currency', $budgetLineColumns);
        $this->assertContains('amount_minor', $budgetLineColumns);
        $this->assertContains('notes', $budgetLineColumns);

        // Strict No Multi-Tenant Invariant: Zero tenancy or scoping columns
        $bannedColumns = ['company_id', 'tenant_id', 'branch_id', 'department_id'];
        foreach ($bannedColumns as $banned) {
            $this->assertNotContains($banned, $budgetColumns, "Table [budget] must not contain [{$banned}]");
            $this->assertNotContains($banned, $budgetLineColumns, "Table [budget_line] must not contain [{$banned}]");
        }
    }

    public function test_user_without_budgeting_view_or_financials_cannot_view_budgets_index(): void
    {
        $noPermUser = User::factory()->create();
        $this->actingAs($noPermUser)->get('/budgeting/budgets')->assertForbidden();

        $budgetOnlyUser = User::factory()->create();
        $budgetOnlyUser->givePermissionTo('budgeting.view');
        $this->actingAs($budgetOnlyUser)->get('/budgeting/budgets')->assertForbidden();

        $financialsOnlyUser = User::factory()->create();
        $financialsOnlyUser->givePermissionTo('view_financials');
        $this->actingAs($financialsOnlyUser)->get('/budgeting/budgets')->assertForbidden();

        $this->actingAs($this->authorizedUser)->get('/budgeting/budgets')->assertOk();
    }

    public function test_database_enforces_single_active_budget_per_fiscal_year(): void
    {
        Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-ACTIVE-A',
            'version_code' => 'V1',
            'name' => ['en' => 'Active Budget A'],
            'status' => 'active',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        $this->expectException(QueryException::class);

        Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-ACTIVE-B',
            'version_code' => 'V2',
            'name' => ['en' => 'Active Budget B'],
            'status' => 'active',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);
    }

    public function test_can_create_draft_budget_with_monthly_lines(): void
    {
        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-2026-OPEX',
            'version_code' => 'V1',
            'name' => ['en' => 'Operating Budget 2026', 'ar' => 'الموازنة التشغيلية 2026'],
            'description' => 'Annual OPEX Budget',
            'default_currency' => 'EGP',
            'lines' => [
                [
                    'financial_period_id' => (string) $this->period1->id,
                    'account_id' => (string) $this->expenseAccount1->id,
                    'project_id' => (string) $this->project->id,
                    'cost_center_id' => (string) $this->costCenter->id,
                    'currency' => 'EGP',
                    'amount_minor' => 5000000,
                    'notes' => 'Salaries January',
                ],
                [
                    'financial_period_id' => (string) $this->period2->id,
                    'account_id' => (string) $this->expenseAccount2->id,
                    'project_id' => null,
                    'cost_center_id' => (string) $this->costCenter->id,
                    'currency' => 'EGP',
                    'amount_minor' => 1200000,
                    'notes' => 'Rent February',
                ],
            ],
        ]);

        $response->assertRedirect('/budgeting/budgets');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('budget', [
            'code' => 'BDG-2026-OPEX',
            'version_code' => 'V1',
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
            'created_by' => $this->authorizedUser->id,
        ]);

        $budget = Budget::query()->where('code', 'BDG-2026-OPEX')->firstOrFail();
        $this->assertCount(2, $budget->lines);

        $this->assertDatabaseHas('budget_line', [
            'budget_id' => $budget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'project_id' => $this->project->id,
            'cost_center_id' => $this->costCenter->id,
            'amount_minor' => 5000000,
        ]);
    }

    public function test_budget_code_must_be_unique(): void
    {
        Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-DUP',
            'version_code' => 'V1',
            'name' => ['en' => 'Existing Budget'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-DUP',
            'version_code' => 'V2',
            'name' => ['en' => 'Duplicate Code Budget'],
            'default_currency' => 'EGP',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_version_code_must_be_unique_per_fiscal_year(): void
    {
        Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-V1-A',
            'version_code' => 'V1',
            'name' => ['en' => 'Budget 1'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        // Duplicate version code under the same fiscal year must fail
        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-V1-B',
            'version_code' => 'V1',
            'name' => ['en' => 'Budget 2'],
            'default_currency' => 'EGP',
        ]);

        $response->assertSessionHasErrors('version_code');

        // Same version code under a DIFFERENT fiscal year must succeed
        $fy2027 = FiscalYear::query()->create([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'open',
        ]);

        $response2 = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $fy2027->id,
            'code' => 'BDG-2027-V1',
            'version_code' => 'V1',
            'name' => ['en' => '2027 Budget V1'],
            'default_currency' => 'EGP',
        ]);

        $response2->assertRedirect('/budgeting/budgets');
        $this->assertDatabaseHas('budget', ['code' => 'BDG-2027-V1', 'version_code' => 'V1']);
    }

    public function test_cannot_create_line_with_period_outside_budget_fiscal_year(): void
    {
        $fy2027 = FiscalYear::query()->create([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'open',
        ]);

        $period2027 = FinancialPeriod::query()->create([
            'fiscal_year_id' => $fy2027->id,
            'month' => 1,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-MISMATCH-PERIOD',
            'version_code' => 'V1',
            'name' => ['en' => 'Mismatch Period'],
            'default_currency' => 'EGP',
            'lines' => [
                [
                    'financial_period_id' => (string) $period2027->id,
                    'account_id' => (string) $this->expenseAccount1->id,
                    'currency' => 'EGP',
                    'amount_minor' => 100000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.financial_period_id');
    }

    public function test_cannot_create_line_with_inactive_account(): void
    {
        $inactiveAccount = Account::query()->create([
            'account_type_id' => $this->expenseAccount1->account_type_id,
            'code' => '599999',
            'name' => ['en' => 'Inactive Account'],
            'type' => 'expense',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-INACTIVE-ACC',
            'version_code' => 'V1',
            'name' => ['en' => 'Inactive Acc Test'],
            'default_currency' => 'EGP',
            'lines' => [
                [
                    'financial_period_id' => (string) $this->period1->id,
                    'account_id' => (string) $inactiveAccount->id,
                    'currency' => 'EGP',
                    'amount_minor' => 100000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.account_id');
    }

    public function test_cannot_create_line_with_inactive_project(): void
    {
        $inactiveProject = Project::query()->create([
            'code' => 'PRJ-INACTIVE',
            'name' => ['en' => 'Inactive Project'],
            'status' => 'cancelled',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-INACTIVE-PRJ',
            'version_code' => 'V1',
            'name' => ['en' => 'Inactive Prj Test'],
            'default_currency' => 'EGP',
            'lines' => [
                [
                    'financial_period_id' => (string) $this->period1->id,
                    'account_id' => (string) $this->expenseAccount1->id,
                    'project_id' => (string) $inactiveProject->id,
                    'currency' => 'EGP',
                    'amount_minor' => 100000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.project_id');
    }

    public function test_cannot_create_line_with_inactive_cost_center(): void
    {
        $inactiveCostCenter = CostCenter::query()->create([
            'code' => 'CC-INACTIVE',
            'name' => ['en' => 'Inactive Cost Center'],
            'category' => 'other',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-INACTIVE-CC',
            'version_code' => 'V1',
            'name' => ['en' => 'Inactive CC Test'],
            'default_currency' => 'EGP',
            'lines' => [
                [
                    'financial_period_id' => (string) $this->period1->id,
                    'account_id' => (string) $this->expenseAccount1->id,
                    'cost_center_id' => (string) $inactiveCostCenter->id,
                    'currency' => 'EGP',
                    'amount_minor' => 100000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.cost_center_id');
    }

    public function test_cannot_create_line_with_negative_amount(): void
    {
        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-NEG-AMT',
            'version_code' => 'V1',
            'name' => ['en' => 'Negative Amount Test'],
            'default_currency' => 'EGP',
            'lines' => [
                [
                    'financial_period_id' => (string) $this->period1->id,
                    'account_id' => (string) $this->expenseAccount1->id,
                    'currency' => 'EGP',
                    'amount_minor' => -500,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.amount_minor');
    }

    public function test_cannot_create_duplicate_line_tuple_in_same_budget(): void
    {
        $response = $this->actingAs($this->authorizedUser)->post('/budgeting/budgets', [
            'fiscal_year_id' => (string) $this->fiscalYear->id,
            'code' => 'BDG-DUP-TUPLE',
            'version_code' => 'V1',
            'name' => ['en' => 'Duplicate Line Tuple Test'],
            'default_currency' => 'EGP',
            'lines' => [
                [
                    'financial_period_id' => (string) $this->period1->id,
                    'account_id' => (string) $this->expenseAccount1->id,
                    'project_id' => (string) $this->project->id,
                    'cost_center_id' => (string) $this->costCenter->id,
                    'currency' => 'EGP',
                    'amount_minor' => 100000,
                ],
                [
                    'financial_period_id' => (string) $this->period1->id,
                    'account_id' => (string) $this->expenseAccount1->id,
                    'project_id' => (string) $this->project->id,
                    'cost_center_id' => (string) $this->costCenter->id,
                    'currency' => 'EGP',
                    'amount_minor' => 200000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.1');
    }

    public function test_can_update_draft_budget_and_replace_lines(): void
    {
        $budget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-UPDATE',
            'version_code' => 'V1',
            'name' => ['en' => 'Initial Budget Name'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
            'created_by' => $this->authorizedUser->id,
        ]);

        BudgetLine::query()->create([
            'budget_id' => $budget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ]);

        $response = $this->actingAs($this->authorizedUser)->patch("/budgeting/budgets/{$budget->id}", [
            'name' => ['en' => 'Updated Budget Name', 'ar' => 'اسم الموازنة المحدث'],
            'description' => 'Updated description',
            'lock_version' => 1,
            'lines' => [
                [
                    'financial_period_id' => (string) $this->period2->id,
                    'account_id' => (string) $this->expenseAccount2->id,
                    'currency' => 'USD',
                    'amount_minor' => 350000,
                    'notes' => 'New line in USD',
                ],
            ],
        ]);

        $response->assertRedirect('/budgeting/budgets');

        $budget->refresh();
        $this->assertSame('Updated Budget Name', $budget->getTranslation('name', 'en'));
        $this->assertSame(2, $budget->lock_version);
        $this->assertCount(1, $budget->lines);

        $line = $budget->lines->first();
        $this->assertSame($this->period2->id, $line->financial_period_id);
        $this->assertSame($this->expenseAccount2->id, $line->account_id);
        $this->assertSame('USD', $line->currency);
        $this->assertSame(350000, $line->amount_minor);
    }

    public function test_budget_fiscal_year_cannot_be_changed_after_creation(): void
    {
        $budget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-FY-LOCKED',
            'version_code' => 'V1',
            'name' => ['en' => 'Fiscal Year Locked'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        $otherFiscalYear = FiscalYear::query()->create([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->authorizedUser)->patch("/budgeting/budgets/{$budget->id}", [
            'fiscal_year_id' => (string) $otherFiscalYear->id,
            'lock_version' => 1,
        ]);

        $response->assertSessionHasErrors('fiscal_year_id');

        $budget->refresh();
        $this->assertSame($this->fiscalYear->id, $budget->fiscal_year_id);
    }

    public function test_optimistic_locking_prevents_stale_update(): void
    {
        $budget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-STALE',
            'version_code' => 'V1',
            'name' => ['en' => 'Lock Test'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 3,
        ]);

        $response = $this->actingAs($this->authorizedUser)->patch("/budgeting/budgets/{$budget->id}", [
            'name' => ['en' => 'Stale Update Attempt'],
            'lock_version' => 2, // Outdated lock version
        ]);

        $response->assertSessionHasErrors('lock_version');
    }

    public function test_can_submit_valid_draft_budget(): void
    {
        $budget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-SUBMIT',
            'version_code' => 'V1',
            'name' => ['en' => 'To Submit'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        BudgetLine::query()->create([
            'budget_id' => $budget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 450000,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$budget->id}/submit", [
            'lock_version' => 1,
        ]);

        $response->assertRedirect('/budgeting/budgets');

        $budget->refresh();
        $this->assertSame('submitted', $budget->status);
        $this->assertSame($this->authorizedUser->id, $budget->submitted_by);
        $this->assertNotNull($budget->submitted_at);
        $this->assertSame(2, $budget->lock_version);
    }

    public function test_cannot_submit_empty_or_zero_amount_budget(): void
    {
        $emptyBudget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-EMPTY',
            'version_code' => 'V1',
            'name' => ['en' => 'Empty'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$emptyBudget->id}/submit", [
            'lock_version' => 1,
        ]);

        $response->assertSessionHasErrors('lines');

        // Also test zero amount across lines
        BudgetLine::query()->create([
            'budget_id' => $emptyBudget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 0,
        ]);

        $response2 = $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$emptyBudget->id}/submit", [
            'lock_version' => 1,
        ]);

        $response2->assertSessionHasErrors('lines');
    }

    public function test_can_approve_submitted_budget(): void
    {
        $budget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-APPROVE',
            'version_code' => 'V1',
            'name' => ['en' => 'To Approve'],
            'status' => 'submitted',
            'default_currency' => 'EGP',
            'submitted_by' => $this->authorizedUser->id,
            'submitted_at' => now(),
            'lock_version' => 2,
        ]);

        BudgetLine::query()->create([
            'budget_id' => $budget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 500000,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$budget->id}/approve", [
            'lock_version' => 2,
        ]);

        $response->assertRedirect('/budgeting/budgets');

        $budget->refresh();
        $this->assertSame('approved', $budget->status);
        $this->assertSame($this->authorizedUser->id, $budget->approved_by);
        $this->assertNotNull($budget->approved_at);
        $this->assertSame(3, $budget->lock_version);
    }

    public function test_cannot_approve_or_activate_budget_without_positive_lines(): void
    {
        $submittedBudget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-APPROVE-ZERO',
            'version_code' => 'V1',
            'name' => ['en' => 'Submitted Zero'],
            'status' => 'submitted',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        BudgetLine::query()->create([
            'budget_id' => $submittedBudget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 0,
        ]);

        $this->actingAs($this->authorizedUser)
            ->post("/budgeting/budgets/{$submittedBudget->id}/approve", ['lock_version' => 1])
            ->assertSessionHasErrors('lines');

        $approvedBudget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-ACTIVATE-ZERO',
            'version_code' => 'V2',
            'name' => ['en' => 'Approved Zero'],
            'status' => 'approved',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        BudgetLine::query()->create([
            'budget_id' => $approvedBudget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 0,
        ]);

        $this->actingAs($this->authorizedUser)
            ->post("/budgeting/budgets/{$approvedBudget->id}/activate", [
                'lock_version' => 1,
                'confirm_action' => 'ACTIVATE_BUDGET',
                'reason' => 'Activating budget test with zero lines',
            ])
            ->assertSessionHasErrors('lines');
    }

    public function test_can_activate_approved_budget_and_auto_archive_previous_active_budget(): void
    {
        // Existing active budget for 2026
        $activeBudgetA = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-2026-V1',
            'version_code' => 'V1',
            'name' => ['en' => 'Budget V1 Active'],
            'status' => 'active',
            'default_currency' => 'EGP',
            'lock_version' => 4,
        ]);

        // New approved budget revision for 2026
        $approvedBudgetB = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-2026-V2',
            'version_code' => 'V2',
            'name' => ['en' => 'Budget V2 Approved'],
            'status' => 'approved',
            'default_currency' => 'EGP',
            'lock_version' => 3,
        ]);

        BudgetLine::query()->create([
            'budget_id' => $approvedBudgetB->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 250000,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$approvedBudgetB->id}/activate", [
            'lock_version' => 3,
            'confirm_action' => 'ACTIVATE_BUDGET',
            'reason' => 'Activating new budget revision',
        ]);

        $response->assertRedirect('/budgeting/budgets');

        $approvedBudgetB->refresh();
        $this->assertSame('active', $approvedBudgetB->status);
        $this->assertSame($this->authorizedUser->id, $approvedBudgetB->activated_by);
        $this->assertNotNull($approvedBudgetB->activated_at);

        // Previous active budget A must now be archived
        $activeBudgetA->refresh();
        $this->assertSame('archived', $activeBudgetA->status);
        $this->assertSame($this->authorizedUser->id, $activeBudgetA->archived_by);
        $this->assertNotNull($activeBudgetA->archived_at);
    }

    public function test_can_cancel_draft_or_submitted_budget(): void
    {
        $budget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-CANCEL',
            'version_code' => 'V1',
            'name' => ['en' => 'To Cancel'],
            'status' => 'draft',
            'default_currency' => 'EGP',
            'lock_version' => 1,
        ]);

        $response = $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$budget->id}/cancel", [
            'lock_version' => 1,
            'confirm_action' => 'CANCEL_BUDGET',
            'reason' => 'Cancelling draft budget record',
        ]);

        $response->assertRedirect('/budgeting/budgets');

        $budget->refresh();
        $this->assertSame('cancelled', $budget->status);
        $this->assertSame($this->authorizedUser->id, $budget->cancelled_by);
        $this->assertNotNull($budget->cancelled_at);

        // Cannot submit or approve cancelled budget
        $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$budget->id}/submit")->assertSessionHasErrors('status');
        $this->actingAs($this->authorizedUser)->post("/budgeting/budgets/{$budget->id}/approve")->assertSessionHasErrors('status');
    }

    public function test_non_draft_budget_cannot_be_edited_or_deleted(): void
    {
        $submittedBudget = Budget::query()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => 'BDG-LOCKED',
            'version_code' => 'V1',
            'name' => ['en' => 'Locked Non Draft'],
            'status' => 'submitted',
            'default_currency' => 'EGP',
            'lock_version' => 2,
        ]);

        BudgetLine::query()->create([
            'budget_id' => $submittedBudget->id,
            'financial_period_id' => $this->period1->id,
            'account_id' => $this->expenseAccount1->id,
            'currency' => 'EGP',
            'amount_minor' => 100000,
        ]);

        // Attempt edit must fail
        $response = $this->actingAs($this->authorizedUser)->patch("/budgeting/budgets/{$submittedBudget->id}", [
            'name' => ['en' => 'Mutated Name'],
            'lock_version' => 2,
        ]);
        $response->assertSessionHasErrors('budget');

        // Attempt delete must fail
        $response2 = $this->actingAs($this->authorizedUser)->delete("/budgeting/budgets/{$submittedBudget->id}");
        $response2->assertSessionHasErrors('budget');

        $this->assertDatabaseHas('budget', ['id' => $submittedBudget->id]);
    }
}
