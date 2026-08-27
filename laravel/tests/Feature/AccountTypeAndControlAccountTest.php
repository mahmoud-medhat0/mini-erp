<?php

namespace Tests\Feature;

use App\Application\Accounting\JournalDraftService;
use App\Application\Accounting\PeriodService;
use App\Application\Accounting\PostingEngine;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\AccountType;
use App\Models\FinancialPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountTypeAndControlAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $fiscalYear;

    private FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(AccountTypeSeeder::class);

        $this->user = User::factory()->create();

        // Give view, create, delete, & account_types permissions for accounting
        $viewPerm = Permission::firstOrCreate(['name' => 'accounting.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'accounting.create', 'guard_name' => 'web']);
        $deletePerm = Permission::firstOrCreate(['name' => 'accounting.delete', 'guard_name' => 'web']);
        $postPerm = Permission::firstOrCreate(['name' => 'accounting.post', 'guard_name' => 'web']);
        $accTypesPerm = Permission::firstOrCreate(['name' => 'accounting.account_types', 'guard_name' => 'web']);

        $this->user->givePermissionTo([$viewPerm, $createPerm, $deletePerm, $postPerm, $accTypesPerm]);

        $periodService = app(PeriodService::class);
        $this->fiscalYear = $periodService->createFiscalYear(2026, '2026-01-01', '2026-12-31');
        $this->period = $this->fiscalYear->periods()->first();
    }

    public function test_account_type_table_exists_and_is_seeded(): void
    {
        $this->assertTrue(Schema::hasTable('account_type'));
        $this->assertGreaterThanOrEqual(11, AccountType::count());
        $this->assertGreaterThanOrEqual(8, AccountCategory::count());
        $this->assertSame(0, AccountType::whereNull('account_category_id')->count());
        $this->assertSame(1, AccountType::where('code', 'CONTRA_REVENUE')->count());
        $this->assertSame(0, AccountType::where('code', 'REVENUE_CONTRA')->count());

        $assetCurrent = AccountType::where('code', 'ASSET_CURRENT')->first();
        $this->assertNotNull($assetCurrent);
        $this->assertNotNull($assetCurrent->account_category_id);
        $this->assertEquals('debit', $assetCurrent->normal_balance);
        $this->assertEquals('balance_sheet', $assetCurrent->statement_type);
        $this->assertEquals('asset', $assetCurrent->category);
        $this->assertTrue($assetCurrent->is_system);
    }

    public function test_account_type_relationships(): void
    {
        $assetType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail();

        $group = AccountGroup::create([
            'id' => (string) Str::uuid(),
            'code' => '1099',
            'name' => ['en' => 'Test Group', 'ar' => 'مجموعة اختباري'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
        ]);

        $account = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '109901',
            'name' => ['en' => 'Test Account', 'ar' => 'حساب اختباري'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'account_group_id' => $group->id,
            'currency' => 'EGP',
        ]);

        $this->assertTrue($assetType->groups->contains($group));
        $this->assertTrue($assetType->accounts->contains($account));
        $this->assertEquals($assetType->id, $group->accountType->id);
        $this->assertEquals($assetType->id, $account->accountType->id);
        $this->assertNotNull($account->currencyRef);
        $this->assertEquals('EGP', $account->currencyRef->code);
    }

    public function test_account_group_creation_requires_valid_account_type_id(): void
    {
        $response = $this->actingAs($this->user)->post('/accounting/coa/groups', [
            'code' => '8000',
            'name_en' => 'Invalid Group',
            'name_ar' => 'مجموعة غير صالحة',
            'account_type_id' => (string) Str::uuid(), // non-existent UUID
        ]);

        $response->assertSessionHasErrors(['account_type_id']);
    }

    public function test_account_creation_requires_valid_account_type_id(): void
    {
        $response = $this->actingAs($this->user)->post('/accounting/coa/accounts', [
            'code' => '800001',
            'name_en' => 'Invalid Account',
            'name_ar' => 'حساب غير صالح',
            'account_type_id' => 'not-a-uuid',
            'currency' => 'EGP',
        ]);

        $response->assertSessionHasErrors(['account_type_id']);
    }

    public function test_account_group_type_syncs_with_account_type_category(): void
    {
        $liabilityType = AccountType::where('code', 'LIABILITY_CURRENT')->firstOrFail();

        $response = $this->actingAs($this->user)->post('/accounting/coa/groups', [
            'code' => '2099',
            'name_en' => 'Sync Group',
            'name_ar' => 'مجموعة مزامنة',
            'account_type_id' => $liabilityType->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('account_group', [
            'code' => '2099',
            'account_type_id' => $liabilityType->id,
            'type' => 'liability',
        ]);
    }

    public function test_account_type_cannot_be_deleted_if_system(): void
    {
        $systemType = AccountType::where('is_system', true)->firstOrFail();

        $response = $this->actingAs($this->user)->delete("/accounting/account-types/{$systemType->id}");

        $response->assertSessionHasErrors(['account_type']);
        $this->assertDatabaseHas('account_type', ['id' => $systemType->id]);
    }

    public function test_account_type_cannot_be_deleted_if_used_by_accounts_or_groups(): void
    {
        $customType = AccountType::create([
            'id' => (string) Str::uuid(),
            'code' => 'CUSTOM_EXPENSE',
            'name' => ['en' => 'Custom Expense', 'ar' => 'مصروف مخصص'],
            'normal_balance' => 'debit',
            'statement_type' => 'income_statement',
            'category' => 'expense',
            'is_system' => false,
        ]);

        Account::create([
            'id' => (string) Str::uuid(),
            'code' => '599901',
            'name' => ['en' => 'Used Account', 'ar' => 'حساب مستخدم'],
            'account_type_id' => $customType->id,
            'type' => 'expense',
            'nature' => 'debit',
            'currency' => 'EGP',
        ]);

        $response = $this->actingAs($this->user)->delete("/accounting/account-types/{$customType->id}");

        $response->assertSessionHasErrors(['account_type']);
        $this->assertDatabaseHas('account_type', ['id' => $customType->id]);
    }

    public function test_unused_custom_account_type_can_be_deleted(): void
    {
        $customType = AccountType::create([
            'id' => (string) Str::uuid(),
            'code' => 'CUSTOM_UNUSED',
            'name' => ['en' => 'Custom Unused', 'ar' => 'مخصص غير مستخدم'],
            'normal_balance' => 'debit',
            'statement_type' => 'income_statement',
            'category' => 'expense',
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->user)->delete("/accounting/account-types/{$customType->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('account_type', ['id' => $customType->id]);
    }

    public function test_account_default_nature_follows_account_type_normal_balance(): void
    {
        $equityType = AccountType::where('code', 'EQUITY')->firstOrFail();

        $response = $this->actingAs($this->user)->post('/accounting/coa/accounts', [
            'code' => '399901',
            'name_en' => 'Equity Account',
            'name_ar' => 'حساب حقوق ملكية',
            'account_type_id' => $equityType->id,
            'currency' => 'EGP',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('account', [
            'code' => '399901',
            'nature' => 'credit',
        ]);
    }

    public function test_account_group_and_account_type_mismatch_is_rejected(): void
    {
        $assetType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail();
        $liabilityType = AccountType::where('code', 'LIABILITY_CURRENT')->firstOrFail();

        $liabilityGroup = AccountGroup::create([
            'id' => (string) Str::uuid(),
            'code' => '2222',
            'name' => ['en' => 'Liability Group', 'ar' => 'مجموعة التزامات'],
            'account_type_id' => $liabilityType->id,
            'type' => 'liability',
        ]);

        $response = $this->actingAs($this->user)->post('/accounting/coa/accounts', [
            'code' => '111199',
            'name_en' => 'Mismatched Account',
            'name_ar' => 'حساب غير متوافق',
            'account_type_id' => $assetType->id,
            'account_group_id' => $liabilityGroup->id,
            'currency' => 'EGP',
        ]);

        $response->assertSessionHasErrors(['account_group_id']);
    }

    public function test_manual_posting_to_control_account_is_blocked_without_override(): void
    {
        $assetType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail();

        $cashAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '110001',
            'name' => ['en' => 'Cash', 'ar' => 'نقدية'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
        ]);

        $controlAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '120001',
            'name' => ['en' => 'AR Control', 'ar' => 'مراقبة عملاء'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
        ]);

        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft([
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'currency' => 'EGP',
            'description' => 'Manual posting to control account',
        ], [
            ['account_id' => $cashAccount->id, 'debit_minor' => 1000, 'credit_minor' => 0],
            ['account_id' => $controlAccount->id, 'debit_minor' => 0, 'credit_minor' => 1000],
        ], $this->user->id);

        $postingEngine = app(PostingEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Direct manual posting to control account');

        $postingEngine->post($entry, $this->user->id, false);
    }

    public function test_override_permission_allows_explicit_control_posting_path(): void
    {
        $assetType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail();

        $cashAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '110002',
            'name' => ['en' => 'Cash 2', 'ar' => 'نقدية 2'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
        ]);

        $controlAccount = Account::create([
            'id' => (string) Str::uuid(),
            'code' => '120002',
            'name' => ['en' => 'AR Control 2', 'ar' => 'مراقبة عملاء 2'],
            'account_type_id' => $assetType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_control' => true,
            'allow_manual_posting' => false,
        ]);

        $draftService = app(JournalDraftService::class);
        $entry = $draftService->createDraft([
            'financial_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'currency' => 'EGP',
            'description' => 'Manual override posting to control account',
        ], [
            ['account_id' => $cashAccount->id, 'debit_minor' => 2000, 'credit_minor' => 0],
            ['account_id' => $controlAccount->id, 'debit_minor' => 0, 'credit_minor' => 2000],
        ], $this->user->id);

        $postingEngine = app(PostingEngine::class);

        $postedEntry = $postingEngine->post($entry, $this->user->id, true);

        $this->assertEquals('posted', $postedEntry->status);
        $this->assertDatabaseHas('journal_entry', [
            'id' => $entry->id,
            'status' => 'posted',
        ]);
    }

    public function test_no_company_id_or_branch_id_or_tenant_scope_introduced(): void
    {
        $columns = Schema::getColumnListing('account_type');

        $this->assertNotContains('company_id', $columns);
        $this->assertNotContains('branch_id', $columns);
        $this->assertNotContains('tenant_id', $columns);
        $this->assertNotContains('project_id', $columns);
    }
}
