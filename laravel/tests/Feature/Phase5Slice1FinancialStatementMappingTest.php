<?php

namespace Tests\Feature;

use App\Application\Accounting\FinancialStatementMappingService;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\FinancialStatementLine;
use App\Models\User;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\FinancialStatementLineSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase5Slice1FinancialStatementMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unprivilegedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(AccountCategorySeeder::class);
        $this->seed(AccountTypeSeeder::class);

        $this->adminUser = User::factory()->create();
        Permission::findOrCreate('accounting.mappings');
        $this->adminUser->givePermissionTo('accounting.mappings');

        $this->unprivilegedUser = User::factory()->create();
    }

    public function test_schema_and_no_tenant_columns(): void
    {
        $this->assertTrue(Schema::hasTable('financial_statement_line'));
        $this->assertTrue(Schema::hasColumn('account', 'financial_statement_line_id'));

        $this->assertFalse(Schema::hasColumn('financial_statement_line', 'company_id'), 'company_id must not exist in financial_statement_line');
        $this->assertFalse(Schema::hasColumn('financial_statement_line', 'branch_id'), 'branch_id must not exist in financial_statement_line');
        $this->assertFalse(Schema::hasColumn('financial_statement_line', 'tenant_id'), 'tenant_id must not exist in financial_statement_line');
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(FinancialStatementLineSeeder::class);
        $countFirst = FinancialStatementLine::count();
        $this->assertEquals(11, $countFirst);

        // Run seeder second time
        $this->seed(FinancialStatementLineSeeder::class);
        $this->assertEquals($countFirst, FinancialStatementLine::count());
    }

    public function test_relationships(): void
    {
        $this->seed(FinancialStatementLineSeeder::class);

        /** @var FinancialStatementLine $line */
        $line = FinancialStatementLine::where('code', 'ASSET_CURRENT')->firstOrFail();

        $accountType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail();
        /** @var Account $account */
        $account = Account::create([
            'code' => '1099-TEST-REL',
            'name' => ['en' => 'Test Asset Account', 'ar' => 'حساب أصول تجريبي'],
            'account_type_id' => $accountType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'financial_statement_line_id' => $line->id,
        ]);

        $this->assertEquals($line->id, $account->financialStatementLine->id);
        $this->assertTrue($line->accounts->pluck('id')->contains($account->id));
    }

    public function test_create_statement_line_validations(): void
    {
        /** @var FinancialStatementMappingService $service */
        $service = app(FinancialStatementMappingService::class);

        // 1. Code required
        try {
            $service->createStatementLine([
                'code' => '',
                'statement_type' => 'balance_sheet',
                'section_code' => 'current_assets',
                'name' => 'Test Line',
                'normal_balance' => 'debit',
            ], $this->adminUser->id);
            $this->fail('Expected ValidationException on empty code.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        // 2. Duplicate code rejection
        $service->createStatementLine([
            'code' => 'CUSTOM_LINE_1',
            'statement_type' => 'balance_sheet',
            'section_code' => 'current_assets',
            'name' => ['en' => 'Custom Line 1'],
            'normal_balance' => 'debit',
        ], $this->adminUser->id);

        try {
            $service->createStatementLine([
                'code' => 'CUSTOM_LINE_1',
                'statement_type' => 'balance_sheet',
                'section_code' => 'current_assets',
                'name' => ['en' => 'Custom Line 1 Dup'],
                'normal_balance' => 'debit',
            ], $this->adminUser->id);
            $this->fail('Expected ValidationException on duplicate code.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        // 3. Invalid statement type
        try {
            $service->createStatementLine([
                'code' => 'INVALID_TYPE_LINE',
                'statement_type' => 'invalid_type',
                'section_code' => 'current_assets',
                'name' => ['en' => 'Invalid Type'],
                'normal_balance' => 'debit',
            ], $this->adminUser->id);
            $this->fail('Expected ValidationException on invalid statement_type.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('statement_type', $e->errors());
        }
    }

    public function test_system_line_delete_protection(): void
    {
        $this->seed(FinancialStatementLineSeeder::class);
        $systemLine = FinancialStatementLine::where('is_system', true)->firstOrFail();

        /** @var FinancialStatementMappingService $service */
        $service = app(FinancialStatementMappingService::class);

        try {
            $service->deleteStatementLine($systemLine->id, $this->adminUser->id);
            $this->fail('Expected ValidationException when deleting system statement line.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('line', $e->errors());
        }
    }

    public function test_in_use_line_delete_protection(): void
    {
        /** @var FinancialStatementMappingService $service */
        $service = app(FinancialStatementMappingService::class);

        $customLine = $service->createStatementLine([
            'code' => 'CUSTOM_ASSET_LINE',
            'statement_type' => 'balance_sheet',
            'section_code' => 'current_assets',
            'name' => ['en' => 'Custom Asset Line'],
            'normal_balance' => 'debit',
        ], $this->adminUser->id);

        $accountType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail();
        $account = Account::create([
            'code' => '1098-IN-USE',
            'name' => ['en' => 'In Use Account'],
            'account_type_id' => $accountType->id,
            'type' => 'asset',
            'nature' => 'debit',
            'financial_statement_line_id' => $customLine->id,
        ]);

        try {
            $service->deleteStatementLine($customLine->id, $this->adminUser->id);
            $this->fail('Expected ValidationException when deleting statement line in use.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('line', $e->errors());
        }

        // Unassign account then delete should succeed
        $service->assignAccount($account->id, null, $this->adminUser->id);
        $service->deleteStatementLine($customLine->id, $this->adminUser->id);

        $this->assertDatabaseMissing('financial_statement_line', ['id' => $customLine->id]);
    }

    public function test_account_assignment_statement_type_mismatch_protection(): void
    {
        $this->seed(FinancialStatementLineSeeder::class);

        /** @var FinancialStatementLine $incomeLine */
        $incomeLine = FinancialStatementLine::where('code', 'REVENUE')->firstOrFail(); // income_statement

        $assetAccountType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail(); // balance_sheet
        $assetAccount = Account::create([
            'code' => '1097-ASSET-MISMATCH',
            'name' => ['en' => 'Mismatch Asset Account'],
            'account_type_id' => $assetAccountType->id,
            'type' => 'asset',
            'nature' => 'debit',
        ]);

        /** @var FinancialStatementMappingService $service */
        $service = app(FinancialStatementMappingService::class);

        try {
            $service->assignAccount($assetAccount->id, $incomeLine->id, $this->adminUser->id);
            $this->fail('Expected ValidationException when assigning Asset account to Income Statement line.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('financial_statement_line_id', $e->errors());
        }
    }

    public function test_rbac_authorization(): void
    {
        $this->seed(FinancialStatementLineSeeder::class);

        // 1. Unprivileged user gets 403
        $this->actingAs($this->unprivilegedUser)
            ->get('/accounting/statement-mappings')
            ->assertStatus(403);

        $this->actingAs($this->unprivilegedUser)
            ->post('/accounting/statement-mappings/lines', [
                'code' => 'UNAUTHORIZED_LINE',
                'statement_type' => 'balance_sheet',
                'section_code' => 'current_assets',
                'name_en' => 'Unauthorized Line',
                'normal_balance' => 'debit',
            ])
            ->assertStatus(403);

        // 2. Admin user with accounting.mappings gets 200 OK
        $this->actingAs($this->adminUser)
            ->get('/accounting/statement-mappings')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/FinancialStatementMappings')
                ->has('lines')
                ->has('unmappedAccounts')
            );
    }

    public function test_audit_logging(): void
    {
        /** @var FinancialStatementMappingService $service */
        $service = app(FinancialStatementMappingService::class);

        $line = $service->createStatementLine([
            'code' => 'AUDIT_TEST_LINE',
            'statement_type' => 'balance_sheet',
            'section_code' => 'current_assets',
            'name' => ['en' => 'Audit Test Line'],
            'normal_balance' => 'debit',
        ], $this->adminUser->id);

        $activity = Activity::where('properties->entity_type', 'financial_statement_line')
            ->where('properties->entity_id', $line->id)
            ->where('description', 'financial_statement_line.create')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->adminUser->id, $activity->causer_id);
    }
}
