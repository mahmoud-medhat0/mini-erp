<?php

namespace Tests\Feature;

use App\Application\CostCenters\CostCenterService;
use App\Application\Expenses\ExpenseService;
use App\Application\Projects\ProjectService;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalLine;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase16Slice3ExpenseDimensionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $expenseAccount;

    private Account $cashGlAccount;

    private CashAccount $cashAccount;

    private Supplier $supplier;

    private ExpenseCategory $category;

    private TaxCode $vat14;

    private ExpenseService $expenseService;

    private ProjectService $projectService;

    private CostCenterService $costCenterService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            'expenses.submit',
            'expenses.approve',
            'expenses.post',
            'projects.view',
            'projects.create',
            'projects.edit',
            'projects.delete',
            'costCenters.view',
            'costCenters.create',
            'costCenters.edit',
            'costCenters.delete',
            'view_financials',
        ]);
        $this->actingAs($this->user);

        $this->expenseService = app(ExpenseService::class);
        $this->projectService = app(ProjectService::class);
        $this->costCenterService = app(CostCenterService::class);

        $this->expenseAccount = Account::query()
            ->where('type', 'expense')
            ->where('nature', 'debit')
            ->where('is_control', false)
            ->firstOrFail();

        $this->cashGlAccount = Account::query()->where('code', '1100')->firstOrFail();
        $this->vat14 = TaxCode::query()->where('code', 'VAT_STD_14')->firstOrFail();
        $this->category = ExpenseCategory::query()->where('code', 'GENERAL_ADMIN')->firstOrFail();

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-EXP-1601',
            'name' => ['en' => 'Slice 3 Supplier', 'ar' => 'مورد شريحة 3'],
            'status' => 'active',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->cashAccount = CashAccount::query()->create([
            'code' => 'CASH-EXP-1601',
            'name' => ['en' => 'Slice 3 Cash', 'ar' => 'خزينة شريحة 3'],
            'gl_account_id' => $this->cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);
    }

    public function test_schema_has_expense_line_dimension_columns_and_no_tenant_assumptions(): void
    {
        $this->assertTrue(Schema::hasTable('expense_line'));
        $this->assertTrue(Schema::hasColumn('expense_line', 'project_id'));
        $this->assertTrue(Schema::hasColumn('expense_line', 'cost_center_id'));

        $this->assertFalse(Schema::hasColumn('expense', 'project_id'), 'Expense header must not contain project_id');
        $this->assertFalse(Schema::hasColumn('expense', 'cost_center_id'), 'Expense header must not contain cost_center_id');

        foreach (['expense', 'expense_line', 'expense_category'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must not contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must not contain tenant_id.");
        }

        $this->assertFalse(config('permission.teams'));
    }

    public function test_expense_page_props_include_active_projects_and_cost_centers_and_exclude_inactive(): void
    {
        $activeProject = Project::query()->create([
            'code' => 'PRJ-EXP-ACT',
            'name' => ['en' => 'Active Project', 'ar' => 'مشروع نشط'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $inactiveProject = Project::query()->create([
            'code' => 'PRJ-EXP-INACT',
            'name' => ['en' => 'Inactive Project', 'ar' => 'مشروع غير نشط'],
            'status' => 'on_hold',
            'is_active' => false,
            'lock_version' => 1,
        ]);

        $activeCostCenter = CostCenter::query()->create([
            'code' => 'CC-EXP-ACT',
            'name' => ['en' => 'Active Cost Center', 'ar' => 'مركز تكلفة نشط'],
            'category' => 'operations',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $inactiveCostCenter = CostCenter::query()->create([
            'code' => 'CC-EXP-INACT',
            'name' => ['en' => 'Inactive Cost Center', 'ar' => 'مركز تكلفة غير نشط'],
            'category' => 'sales',
            'is_active' => false,
            'lock_version' => 1,
        ]);

        $response = $this->get('/expenses');
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Expenses/Index')
            ->has('projects')
            ->where('projects', fn ($projects) => collect($projects)->pluck('id')->contains($activeProject->id) &&
                ! collect($projects)->pluck('id')->contains($inactiveProject->id))
            ->has('costCenters')
            ->where('costCenters', fn ($costCenters) => collect($costCenters)->pluck('id')->contains($activeCostCenter->id) &&
                ! collect($costCenters)->pluck('id')->contains($inactiveCostCenter->id))
        );
    }

    public function test_creating_expense_stores_project_and_cost_center_dimensions_on_expense_lines(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-EXP-001',
            'name' => ['en' => 'Office Upgrade', 'ar' => 'تطوير المكتب'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-EXP-001',
            'name' => ['en' => 'IT Operations', 'ar' => 'عمليات تقنية المعلومات'],
            'category' => 'operations',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $expense = $this->expenseService->create([
            'expense_date' => '2026-08-15',
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'description' => 'Software licensing',
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 150000,
                ],
            ],
        ], $this->user->id);

        $this->assertDatabaseHas('expense_line', [
            'expense_id' => $expense->id,
            'project_id' => $project->id,
            'cost_center_id' => $costCenter->id,
            'line_total_minor' => 150000,
        ]);

        $line = $expense->lines->first();
        $this->assertNotNull($line);
        $this->assertEquals($project->id, $line->project_id);
        $this->assertEquals($costCenter->id, $line->cost_center_id);
        $this->assertEquals('PRJ-EXP-001', $line->project->code);
        $this->assertEquals('CC-EXP-001', $line->costCenter->code);
    }

    public function test_editing_draft_expense_preserves_hydrates_and_updates_expense_line_dimensions(): void
    {
        $projectA = Project::query()->create([
            'code' => 'PRJ-EXP-A',
            'name' => ['en' => 'Project A', 'ar' => 'مشروع أ'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $projectB = Project::query()->create([
            'code' => 'PRJ-EXP-B',
            'name' => ['en' => 'Project B', 'ar' => 'مشروع ب'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $costCenterA = CostCenter::query()->create([
            'code' => 'CC-EXP-A',
            'name' => ['en' => 'Cost Center A', 'ar' => 'مركز أ'],
            'category' => 'finance',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $costCenterB = CostCenter::query()->create([
            'code' => 'CC-EXP-B',
            'name' => ['en' => 'Cost Center B', 'ar' => 'مركز ب'],
            'category' => 'sales',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $expense = $this->expenseService->create([
            'expense_date' => '2026-08-15',
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $projectA->id,
                    'cost_center_id' => $costCenterA->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 50000,
                ],
            ],
        ], $this->user->id);

        $updatedExpense = $this->expenseService->update($expense->id, [
            'lock_version' => 1,
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $projectB->id,
                    'cost_center_id' => $costCenterB->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 75000,
                ],
            ],
        ], $this->user->id);

        $line = $updatedExpense->lines->first();
        $this->assertEquals($projectB->id, $line->project_id);
        $this->assertEquals($costCenterB->id, $line->cost_center_id);
        $this->assertEquals(75000, $line->line_total_minor);
    }

    public function test_inactive_project_or_cost_center_cannot_be_used_on_create_or_update(): void
    {
        $inactiveProject = Project::query()->create([
            'code' => 'PRJ-EXP-INACT2',
            'name' => ['en' => 'Inactive Prj', 'ar' => 'مشروع غير نشط'],
            'status' => 'cancelled',
            'is_active' => false,
            'lock_version' => 1,
        ]);

        $inactiveCostCenter = CostCenter::query()->create([
            'code' => 'CC-EXP-INACT2',
            'name' => ['en' => 'Inactive CC', 'ar' => 'مركز غير نشط'],
            'category' => 'other',
            'is_active' => false,
            'lock_version' => 1,
        ]);

        // 1. Create with inactive project rejected
        try {
            $this->expenseService->create([
                'expense_date' => '2026-08-15',
                'settlement_method' => 'cash',
                'cash_account_id' => $this->cashAccount->id,
                'currency' => 'EGP',
                'lines' => [
                    [
                        'expense_category_id' => $this->category->id,
                        'expense_account_id' => $this->expenseAccount->id,
                        'project_id' => $inactiveProject->id,
                        'quantity_e6' => 1000000,
                        'unit_amount_minor' => 10000,
                    ],
                ],
            ], $this->user->id);
            $this->fail('Expected ValidationException for inactive project on create.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines.0.project_id', $e->errors());
            $this->assertEquals('Selected expense project is inactive or missing.', $e->errors()['lines.0.project_id'][0]);
        }

        // 2. Create with inactive cost center rejected
        try {
            $this->expenseService->create([
                'expense_date' => '2026-08-15',
                'settlement_method' => 'cash',
                'cash_account_id' => $this->cashAccount->id,
                'currency' => 'EGP',
                'lines' => [
                    [
                        'expense_category_id' => $this->category->id,
                        'expense_account_id' => $this->expenseAccount->id,
                        'cost_center_id' => $inactiveCostCenter->id,
                        'quantity_e6' => 1000000,
                        'unit_amount_minor' => 10000,
                    ],
                ],
            ], $this->user->id);
            $this->fail('Expected ValidationException for inactive cost center on create.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines.0.cost_center_id', $e->errors());
            $this->assertEquals('Selected expense cost center is inactive or missing.', $e->errors()['lines.0.cost_center_id'][0]);
        }

        // Create valid draft
        $draft = $this->expenseService->create([
            'expense_date' => '2026-08-15',
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 10000,
                ],
            ],
        ], $this->user->id);

        // 3. Update with inactive project rejected
        try {
            $this->expenseService->update($draft->id, [
                'lock_version' => 1,
                'lines' => [
                    [
                        'expense_category_id' => $this->category->id,
                        'expense_account_id' => $this->expenseAccount->id,
                        'project_id' => $inactiveProject->id,
                        'quantity_e6' => 1000000,
                        'unit_amount_minor' => 10000,
                    ],
                ],
            ], $this->user->id);
            $this->fail('Expected ValidationException for inactive project on update.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines.0.project_id', $e->errors());
            $this->assertEquals('Selected expense project is inactive or missing.', $e->errors()['lines.0.project_id'][0]);
        }

        // 4. Update with inactive cost center rejected
        try {
            $this->expenseService->update($draft->id, [
                'lock_version' => 1,
                'lines' => [
                    [
                        'expense_category_id' => $this->category->id,
                        'expense_account_id' => $this->expenseAccount->id,
                        'cost_center_id' => $inactiveCostCenter->id,
                        'quantity_e6' => 1000000,
                        'unit_amount_minor' => 10000,
                    ],
                ],
            ], $this->user->id);
            $this->fail('Expected ValidationException for inactive cost center on update.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines.0.cost_center_id', $e->errors());
            $this->assertEquals('Selected expense cost center is inactive or missing.', $e->errors()['lines.0.cost_center_id'][0]);
        }
    }

    public function test_posting_approved_expense_propagates_dimensions_to_debit_journal_lines_and_ledger_entries(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-EXP-POST',
            'name' => ['en' => 'Server Migration', 'ar' => 'نقل الخوادم'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-EXP-POST',
            'name' => ['en' => 'Infrastructure', 'ar' => 'البنية التحتية'],
            'category' => 'operations',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $expense = $this->expenseService->create([
            'expense_date' => '2026-08-15',
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 300000,
                ],
            ],
        ], $this->user->id);

        $this->expenseService->submit($expense->id, $this->user->id);
        $this->expenseService->approve($expense->id, $this->user->id);
        $posted = $this->expenseService->post($expense->id, $this->user->id);

        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->journal_entry_id);

        // Verify Journal Lines
        $debitLine = JournalLine::query()
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->where('account_id', $this->expenseAccount->id)
            ->firstOrFail();

        $this->assertEquals($project->id, $debitLine->project_id);
        $this->assertEquals($costCenter->id, $debitLine->cost_center_id);
        $this->assertEquals(300000, $debitLine->debit_minor);

        // Verify Ledger Entries
        $debitLedger = LedgerEntry::query()
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->where('account_id', $this->expenseAccount->id)
            ->firstOrFail();

        $this->assertEquals($project->id, $debitLedger->project_id);
        $this->assertEquals($costCenter->id, $debitLedger->cost_center_id);
        $this->assertEquals(300000, $debitLedger->debit_minor);
    }

    public function test_multiple_expense_lines_using_same_account_with_different_dimensions_remain_separate_in_journal_and_ledger(): void
    {
        $projectA = Project::query()->create([
            'code' => 'PRJ-GRP-A',
            'name' => ['en' => 'Project A', 'ar' => 'مشروع أ'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $projectB = Project::query()->create([
            'code' => 'PRJ-GRP-B',
            'name' => ['en' => 'Project B', 'ar' => 'مشروع ب'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-GRP-1',
            'name' => ['en' => 'Operations CC', 'ar' => 'مركز التشغيل'],
            'category' => 'operations',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        // Line 1: projectA + costCenter ($100.00 = 10000 minor)
        // Line 2: projectB + costCenter ($200.00 = 20000 minor)
        // Line 3: projectA + costCenter ($50.00 = 5000 minor) -> groups with Line 1 ($150.00 total)
        $expense = $this->expenseService->create([
            'expense_date' => '2026-08-15',
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $projectA->id,
                    'cost_center_id' => $costCenter->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 10000,
                ],
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $projectB->id,
                    'cost_center_id' => $costCenter->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 20000,
                ],
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $projectA->id,
                    'cost_center_id' => $costCenter->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 5000,
                ],
            ],
        ], $this->user->id);

        $this->expenseService->submit($expense->id, $this->user->id);
        $this->expenseService->approve($expense->id, $this->user->id);
        $posted = $this->expenseService->post($expense->id, $this->user->id);

        // Check Journal Lines: expect exactly 2 debit lines for this expense account
        $debitLines = JournalLine::query()
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->where('account_id', $this->expenseAccount->id)
            ->orderBy('debit_minor')
            ->get();

        $this->assertCount(2, $debitLines);

        $groupA = $debitLines->firstWhere('project_id', $projectA->id);
        $groupB = $debitLines->firstWhere('project_id', $projectB->id);

        $this->assertNotNull($groupA);
        $this->assertNotNull($groupB);
        $this->assertEquals(15000, $groupA->debit_minor); // 10000 + 5000
        $this->assertEquals($costCenter->id, $groupA->cost_center_id);
        $this->assertEquals(20000, $groupB->debit_minor);
        $this->assertEquals($costCenter->id, $groupB->cost_center_id);

        // Check Ledger Entries: exactly 2 debit ledger entries
        $ledgerEntries = LedgerEntry::query()
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->where('account_id', $this->expenseAccount->id)
            ->get();

        $this->assertCount(2, $ledgerEntries);
        $ledgerA = $ledgerEntries->firstWhere('project_id', $projectA->id);
        $ledgerB = $ledgerEntries->firstWhere('project_id', $projectB->id);

        $this->assertNotNull($ledgerA);
        $this->assertNotNull($ledgerB);
        $this->assertEquals(15000, $ledgerA->debit_minor);
        $this->assertEquals(20000, $ledgerB->debit_minor);
    }

    public function test_input_tax_and_settlement_journal_and_ledger_lines_remain_untagged(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-TAX-TEST',
            'name' => ['en' => 'Tax Test Project', 'ar' => 'مشروع اختبار الضريبة'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-TAX-TEST',
            'name' => ['en' => 'Tax Test CC', 'ar' => 'مركز اختبار الضريبة'],
            'category' => 'finance',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $expense = $this->expenseService->create([
            'expense_date' => '2026-08-15',
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'tax_code_id' => $this->vat14->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 100000,
                ],
            ],
        ], $this->user->id);

        $this->expenseService->submit($expense->id, $this->user->id);
        $this->expenseService->approve($expense->id, $this->user->id);
        $posted = $this->expenseService->post($expense->id, $this->user->id);

        $allJournalLines = JournalLine::query()
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->get();

        // 3 journal lines: debit expense (100000), debit input tax (14000), credit cash settlement (114000)
        $this->assertCount(3, $allJournalLines);

        $debitExpenseLine = $allJournalLines->firstWhere('account_id', $this->expenseAccount->id);
        $this->assertEquals($project->id, $debitExpenseLine->project_id);
        $this->assertEquals($costCenter->id, $debitExpenseLine->cost_center_id);

        $nonExpenseLines = $allJournalLines->where('account_id', '!=', $this->expenseAccount->id);
        foreach ($nonExpenseLines as $line) {
            $this->assertNull($line->project_id, "Non-expense journal line [{$line->memo}] must have null project_id.");
            $this->assertNull($line->cost_center_id, "Non-expense journal line [{$line->memo}] must have null cost_center_id.");
        }

        // Ledger entries
        $allLedgerEntries = LedgerEntry::query()
            ->where('journal_entry_id', $posted->journal_entry_id)
            ->get();

        $this->assertCount(3, $allLedgerEntries);

        $nonExpenseLedger = $allLedgerEntries->where('account_id', '!=', $this->expenseAccount->id);
        foreach ($nonExpenseLedger as $entry) {
            $this->assertNull($entry->project_id, 'Non-expense ledger entry must have null project_id.');
            $this->assertNull($entry->cost_center_id, 'Non-expense ledger entry must have null cost_center_id.');
        }
    }

    public function test_project_and_cost_center_deletion_is_blocked_when_referenced_by_expense_lines(): void
    {
        $project = Project::query()->create([
            'code' => 'PRJ-DEL-BLOCKED',
            'name' => ['en' => 'Delete Blocked Prj', 'ar' => 'مشروع محمي من الحذف'],
            'status' => 'active',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $costCenter = CostCenter::query()->create([
            'code' => 'CC-DEL-BLOCKED',
            'name' => ['en' => 'Delete Blocked CC', 'ar' => 'مركز محمي من الحذف'],
            'category' => 'administrative',
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $expense = $this->expenseService->create([
            'expense_date' => '2026-08-15',
            'settlement_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'currency' => 'EGP',
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'project_id' => $project->id,
                    'cost_center_id' => $costCenter->id,
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 20000,
                ],
            ],
        ], $this->user->id);

        // Project deletion blocked
        try {
            $this->projectService->delete($project->id, $this->user->id);
            $this->fail('Expected ValidationException when deleting project referenced by expense lines.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('project', $e->errors());
            $this->assertEquals('Cannot delete project referenced by expense lines, journal lines, or ledger entries.', $e->errors()['project'][0]);
        }

        // Cost Center deletion blocked
        try {
            $this->costCenterService->delete($costCenter->id, $this->user->id);
            $this->fail('Expected ValidationException when deleting cost center referenced by expense lines.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cost_center', $e->errors());
            $this->assertEquals('Cannot delete cost center referenced by expense lines, journal lines, or ledger entries.', $e->errors()['cost_center'][0]);
        }
    }

    public function test_ui_source_scan_confirms_clean_react_patterns_and_no_hardcoded_labels(): void
    {
        $tsxPath = resource_path('js/Pages/Expenses/Index.tsx');
        $this->assertFileExists($tsxPath);
        $content = file_get_contents($tsxPath);

        $this->assertFalse(str_contains($content, '<select'), 'Must not contain native <select>');
        $this->assertFalse(str_contains($content, '<option'), 'Must not contain native <option>');
        $this->assertFalse(str_contains($content, 'type="date"'), 'Must not contain native type="date"');
        $this->assertFalse(str_contains($content, "type='date'"), "Must not contain native type='date'");
        $this->assertFalse(str_contains($content, 'window.location.href'), 'Must not contain window.location.href');

        // Check dictionary localization in JSX
        $this->assertStringContainsString('pageDict.project', $content);
        $this->assertStringContainsString('pageDict.costCenter', $content);
        $this->assertStringContainsString('pageDict.selectProject', $content);
        $this->assertStringContainsString('pageDict.selectCostCenter', $content);
    }

    public function test_scope_scan_confirms_no_tenant_or_company_assumptions(): void
    {
        $filesToScan = [
            app_path('Application/Expenses/ExpenseService.php'),
            app_path('Application/Expenses/ExpensePageData.php'),
            app_path('Http/Controllers/ExpenseController.php'),
            app_path('Models/ExpenseLine.php'),
            database_path('migrations/2026_08_28_030000_add_phase16_dimensions_to_expense_lines.php'),
        ];

        foreach ($filesToScan as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);

            $this->assertFalse(str_contains($content, 'company_id'), "File [{$file}] must not contain company_id");
            $this->assertFalse(str_contains($content, 'tenant_id'), "File [{$file}] must not contain tenant_id");
            $this->assertFalse(str_contains($content, 'currentCompany'), "File [{$file}] must not contain currentCompany");
            $this->assertFalse(str_contains($content, 'currentTenant'), "File [{$file}] must not contain currentTenant");
            $this->assertFalse(str_contains($content, 'currentBranch'), "File [{$file}] must not contain currentBranch");
        }
    }
}
