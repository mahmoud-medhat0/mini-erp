<?php

namespace Tests\Feature;

use App\Application\Accounting\PeriodService;
use App\Application\Expenses\ExpenseCategoryService;
use App\Application\Expenses\ExpenseService;
use App\Application\Taxes\TaxPeriodService;
use App\Application\Taxes\TaxReturnService;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\PayableEntry;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase11ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $expenseAccount;

    private Account $cashGlAccount;

    private Account $bankGlAccount;

    private Supplier $supplier;

    private CashAccount $cashAccount;

    private BankAccount $bankAccount;

    private ExpenseCategory $category;

    private TaxCode $vat14;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder']);

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->user->givePermissionTo([
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            'expenses.submit',
            'expenses.approve',
            'expenses.post',
            'view_financials',
        ]);
        $this->actingAs($this->user);

        $this->expenseAccount = Account::query()
            ->where('type', 'expense')
            ->where('nature', 'debit')
            ->where('is_control', false)
            ->firstOrFail();

        $this->cashGlAccount = Account::query()->where('code', '1100')->firstOrFail();
        $this->bankGlAccount = Account::query()->where('code', '1100')->firstOrFail();
        $this->vat14 = TaxCode::query()->where('code', 'VAT_STD_14')->firstOrFail();
        $this->category = ExpenseCategory::query()->where('code', 'GENERAL_ADMIN')->firstOrFail();

        $this->supplier = Supplier::query()->create([
            'code' => 'SUPP-EXP-001',
            'name' => ['en' => 'Expense Supplier', 'ar' => 'مورد مصروف'],
            'status' => 'active',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->cashAccount = CashAccount::query()->create([
            'code' => 'CASH-EXP-001',
            'name' => ['en' => 'Expense Cash', 'ar' => 'خزينة مصروفات'],
            'gl_account_id' => $this->cashGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'code' => 'BANK-EXP-001',
            'name' => ['en' => 'Expense Bank', 'ar' => 'بنك مصروفات'],
            'bank_name' => ['en' => 'Operations Bank', 'ar' => 'بنك التشغيل'],
            'account_number' => 'EXP-001',
            'gl_account_id' => $this->bankGlAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'lock_version' => 1,
        ]);
    }

    public function test_expense_schema_preserves_single_erp_scope_with_only_operational_branch_reference(): void
    {
        $this->assertTrue(Schema::hasTable('expense_category'));
        $this->assertTrue(Schema::hasTable('expense'));
        $this->assertTrue(Schema::hasTable('expense_line'));
        $this->assertTrue(Schema::hasColumn('expense', 'branch_id'));

        foreach (['expense_category', 'expense_line'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'), "Table [{$table}] must not contain branch_id.");
        }

        foreach (['expense_category', 'expense', 'expense_line'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must not contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must not contain tenant_id.");
        }

        $this->assertFalse(config('permission.teams'));
    }

    public function test_expense_category_crud_blocks_deleting_used_categories(): void
    {
        $service = app(ExpenseCategoryService::class);

        $category = $service->create([
            'code' => 'EXP_TEST',
            'name' => ['en' => 'Test Expense', 'ar' => 'مصروف اختبار'],
            'default_expense_account_id' => $this->expenseAccount->id,
            'default_tax_code_id' => $this->vat14->id,
            'requires_attachment' => false,
            'is_active' => true,
        ], $this->user->id);

        $updated = $service->update($category->id, [
            'name' => ['en' => 'Updated Test Expense', 'ar' => 'مصروف اختبار معدل'],
            'lock_version' => $category->lock_version,
        ], $this->user->id);

        $this->assertSame('Updated Test Expense', $updated->getTranslation('name', 'en'));

        $expense = $this->draftExpense(['expense_category_id' => $updated->id]);
        $this->assertSame($updated->id, $expense->lines()->firstOrFail()->expense_category_id);

        $this->expectException(ValidationException::class);
        $service->delete($updated->id, $this->user->id);
    }

    public function test_payable_expense_posts_expense_input_vat_and_payable_subledger(): void
    {
        $expense = $this->draftExpense([
            'tax_code_id' => $this->vat14->id,
            'unit_amount_minor' => 10000,
        ]);

        $posted = $this->postExpense($expense);

        $this->assertSame('posted', $posted->status);
        $this->assertStringStartsWith('EXP-2026-', (string) $posted->number);
        $this->assertSame(10000, $posted->subtotal_minor);
        $this->assertSame(1400, $posted->tax_amount_minor);
        $this->assertSame(11400, $posted->total_minor);

        $journal = JournalEntry::query()->with('lines.account')->where('id', $posted->journal_entry_id)->firstOrFail();
        $this->assertSame('posted', $journal->status);
        $this->assertSame(11400, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(11400, (int) $journal->lines->sum('credit_minor'));

        $inputTaxLine = $journal->lines->first(fn ($line) => $line->account->code === '1300' && $line->debit_minor === 1400);
        $apLine = $journal->lines->first(fn ($line) => $line->account->code === '2100' && $line->credit_minor === 11400);
        $this->assertNotNull($inputTaxLine);
        $this->assertNotNull($apLine);

        $payable = PayableEntry::query()->where('id', $posted->payable_entry_id)->firstOrFail();
        $this->assertSame('expense', $payable->source_type);
        $this->assertSame(11400, $payable->credit_minor);
    }

    public function test_cash_expense_posts_directly_without_payable_entry(): void
    {
        $expense = $this->draftExpense([
            'settlement_method' => 'cash',
            'supplier_id' => null,
            'cash_account_id' => $this->cashAccount->id,
            'payee_name' => 'Office Vendor',
            'unit_amount_minor' => 2500,
        ]);

        $posted = $this->postExpense($expense);

        $this->assertSame('posted', $posted->status);
        $this->assertNull($posted->payable_entry_id);

        $journal = JournalEntry::query()->with('lines.account')->where('id', $posted->journal_entry_id)->firstOrFail();
        $cashCredit = $journal->lines->first(fn ($line) => $line->account_id === $this->cashGlAccount->id && $line->credit_minor === 2500);

        $this->assertNotNull($cashCredit);
        $this->assertSame(2500, (int) $journal->lines->sum('debit_minor'));
        $this->assertSame(2500, (int) $journal->lines->sum('credit_minor'));
    }

    public function test_required_attachment_category_blocks_posting_until_evidence_exists(): void
    {
        $category = app(ExpenseCategoryService::class)->create([
            'code' => 'ATTACH_REQUIRED',
            'name' => ['en' => 'Attachment Required', 'ar' => 'يتطلب مرفق'],
            'default_expense_account_id' => $this->expenseAccount->id,
            'requires_attachment' => true,
            'is_active' => true,
        ], $this->user->id);

        $expense = $this->draftExpense(['expense_category_id' => $category->id]);
        app(ExpenseService::class)->submit($expense->id, $this->user->id);
        app(ExpenseService::class)->approve($expense->id, $this->user->id);

        try {
            app(ExpenseService::class)->post($expense->id, $this->user->id);
            $this->fail('Posting must require attachment evidence for attachment-required categories.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachments', $exception->errors());
        }

        DB::table('attachment')->insert([
            'id' => (string) Str::uuid(),
            'entity_type' => 'expense',
            'entity_id' => $expense->id,
            'file_ref' => 'attachments/expense-proof.txt',
            'name' => 'expense-proof.txt',
            'mime' => 'text/plain',
            'size' => 10,
            'uploaded_by' => $this->user->id,
            'at' => now(),
        ]);

        $posted = app(ExpenseService::class)->post($expense->id, $this->user->id);
        $this->assertSame('posted', $posted->status);
    }

    public function test_filed_tax_period_blocks_tax_affecting_expense_posting(): void
    {
        $period = app(TaxPeriodService::class)->createPeriod([
            'period_label' => '2026-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);
        $return = app(TaxReturnService::class)->generateDraftReturn($period->id, $this->user->id);
        app(TaxReturnService::class)->fileReturn($return->id, $this->user->id);

        $expense = $this->draftExpense([
            'tax_code_id' => $this->vat14->id,
            'unit_amount_minor' => 10000,
        ]);
        app(ExpenseService::class)->submit($expense->id, $this->user->id);
        app(ExpenseService::class)->approve($expense->id, $this->user->id);

        $this->expectException(ValidationException::class);
        app(ExpenseService::class)->post($expense->id, $this->user->id);
    }

    public function test_period_close_readiness_blocks_unposted_expenses(): void
    {
        $expense = $this->draftExpense(['reference' => 'CLOSE-BLOCK-EXP']);
        $period = FinancialPeriod::query()->where('id', $expense->financial_period_id)->firstOrFail();
        $readiness = app(PeriodService::class)->checkCloseReadiness($period);

        $this->assertFalse($readiness['can_close']);
        $this->assertTrue(collect($readiness['blockers'])->contains(fn (array $blocker): bool => $blocker['entity_type'] === 'expense' && $blocker['reason_code'] === 'unposted_expense'));
    }

    public function test_expense_inertia_pages_render_with_expected_props(): void
    {
        $this->withoutVite();

        $this->get('/expenses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->has('categories')
                ->has('expenseAccounts')
                ->has('settlementMethods'));

        $this->get('/expenses/categories')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Categories')
                ->has('categories')
                ->has('expenseAccounts')
                ->has('taxCodes'));
    }

    private function draftExpense(array $lineOverrides = []): Expense
    {
        $dataOverrides = collect($lineOverrides)->only([
            'settlement_method',
            'supplier_id',
            'cash_account_id',
            'bank_account_id',
            'payee_name',
            'reference',
        ])->all();

        $lineData = collect($lineOverrides)->except([
            'settlement_method',
            'supplier_id',
            'cash_account_id',
            'bank_account_id',
            'payee_name',
            'reference',
        ])->all();

        return app(ExpenseService::class)->create([
            'expense_date' => '2026-01-10',
            'due_date' => '2026-01-31',
            'settlement_method' => 'payable',
            'supplier_id' => $this->supplier->id,
            'currency' => 'EGP',
            'reference' => 'EXP-TEST-REF',
            ...$dataOverrides,
            'lines' => [
                [
                    'expense_category_id' => $this->category->id,
                    'expense_account_id' => $this->expenseAccount->id,
                    'description' => 'Expense line',
                    'quantity_e6' => 1000000,
                    'unit_amount_minor' => 5000,
                    'tax_code_id' => null,
                    ...$lineData,
                ],
            ],
        ], $this->user->id);
    }

    private function postExpense(Expense $expense): Expense
    {
        $service = app(ExpenseService::class);
        $service->submit($expense->id, $this->user->id);
        $service->approve($expense->id, $this->user->id);

        return $service->post($expense->id, $this->user->id);
    }
}
