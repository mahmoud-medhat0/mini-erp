<?php

namespace Tests\Feature;

use App\Application\Attachments\AttachmentEntityAuthorizer;
use App\Application\MasterData\BankAccountService;
use App\Application\MasterData\CashAccountService;
use App\Application\MasterData\CustomerService;
use App\Application\MasterData\SupplierService;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\AccountingCoreSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase3Slice1MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(AccountingCoreSeeder::class);
    }

    public function test_spatie_teams_remains_disabled(): void
    {
        $this->assertFalse(config('permission.teams'));
    }

    public function test_master_data_tables_exist_without_tenant_or_company_or_branch_id(): void
    {
        $tables = ['customer', 'supplier', 'cash_account', 'bank_account'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");

            $this->assertFalse(Schema::hasColumn($table, 'company_id'), "Table [{$table}] must NOT contain company_id.");
            $this->assertFalse(Schema::hasColumn($table, 'branch_id'), "Table [{$table}] must NOT contain branch_id.");
            $this->assertFalse(Schema::hasColumn($table, 'tenant_id'), "Table [{$table}] must NOT contain tenant_id.");
        }
    }

    public function test_customer_lifecycle_code_uniqueness_and_audit(): void
    {
        $user = User::factory()->create();
        /** @var CustomerService $service */
        $service = app(CustomerService::class);

        $customer = $service->create([
            'code' => 'CUST-001',
            'name' => ['en' => 'Acme Corp', 'ar' => 'شركة أكمي'],
            'email' => 'contact@acme.com',
            'phone' => '+1234567890',
        ], $user->id);

        $this->assertDatabaseHas('customer', [
            'id' => $customer->id,
            'code' => 'CUST-001',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->assertEquals('Acme Corp', $customer->getTranslation('name', 'en'));

        // Assert code uniqueness validation
        $this->expectException(ValidationException::class);
        $service->create([
            'code' => 'CUST-001',
            'name' => ['en' => 'Duplicate Customer', 'ar' => 'عميل مكرر'],
        ], $user->id);

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'customer')
            ->where('properties->entity_id', $customer->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('create', $activity->event);
    }

    public function test_customer_update_allows_nullable_clear_translation_update_and_lock_increment(): void
    {
        $user = User::factory()->create();
        /** @var CustomerService $service */
        $service = app(CustomerService::class);

        $customer = $service->create([
            'code' => 'CUST-UPD-001',
            'name' => ['en' => 'Customer Before', 'ar' => 'عميل قبل'],
            'email' => 'before@example.com',
            'phone' => '111',
            'address' => 'Old address',
            'tax_number' => 'TAX-BEFORE',
        ], $user->id);

        $updated = $service->update($customer->id, [
            'name' => ['en' => 'Customer After', 'ar' => 'عميل بعد'],
            'status' => 'inactive',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_number' => null,
        ], $customer->lock_version, $user->id);

        $this->assertSame('Customer After', $updated->getTranslation('name', 'en'));
        $this->assertSame('inactive', $updated->status);
        $this->assertNull($updated->email);
        $this->assertNull($updated->phone);
        $this->assertNull($updated->address);
        $this->assertNull($updated->tax_number);
        $this->assertSame(1, $updated->lock_version);
    }

    public function test_supplier_lifecycle_code_uniqueness_and_audit(): void
    {
        $user = User::factory()->create();
        /** @var SupplierService $service */
        $service = app(SupplierService::class);

        $supplier = $service->create([
            'code' => 'SUPP-001',
            'name' => ['en' => 'Global Supplies', 'ar' => 'التوريدات العالمية'],
            'tax_number' => 'TAX-998877',
        ], $user->id);

        $this->assertDatabaseHas('supplier', [
            'id' => $supplier->id,
            'code' => 'SUPP-001',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        // Audit check
        $activity = Activity::query()
            ->where('properties->entity_type', 'supplier')
            ->where('properties->entity_id', $supplier->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('create', $activity->event);
    }

    public function test_customer_and_supplier_reject_invalid_status(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        app(CustomerService::class)->create([
            'code' => 'CUST-BAD-STATUS',
            'name' => ['en' => 'Bad Customer', 'ar' => 'عميل غير صالح'],
            'status' => 'paused',
        ], $user->id);
    }

    public function test_supplier_rejects_invalid_status_on_update(): void
    {
        $user = User::factory()->create();
        /** @var SupplierService $service */
        $service = app(SupplierService::class);

        $supplier = $service->create([
            'code' => 'SUPP-STATUS-001',
            'name' => ['en' => 'Supplier Status', 'ar' => 'حالة المورد'],
        ], $user->id);

        $this->expectException(ValidationException::class);
        $service->update($supplier->id, [
            'status' => null,
        ], $supplier->lock_version, $user->id);
    }

    public function test_cash_account_validates_gl_account_and_currency_and_audit(): void
    {
        $user = User::factory()->create();
        /** @var Account $glAccount */
        $glAccount = Account::query()->where('is_active', true)->firstOrFail();

        /** @var CashAccountService $service */
        $service = app(CashAccountService::class);

        $cash = $service->create([
            'code' => 'CASH-001',
            'name' => ['en' => 'Main Safe', 'ar' => 'الخزينة الرئيسية'],
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
        ], $user->id);

        $this->assertDatabaseHas('cash_account', [
            'id' => $cash->id,
            'code' => 'CASH-001',
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
            'is_active' => true,
        ]);

        // Model relationship check
        $this->assertEquals($glAccount->id, $cash->glAccount->id);
        $this->assertEquals('EGP', $cash->currencyRef->code);

        // Assert audit
        $activity = Activity::query()
            ->where('properties->entity_type', 'cash_account')
            ->where('properties->entity_id', $cash->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('create', $activity->event);
    }

    public function test_cash_account_update_can_deactivate_and_update_translation(): void
    {
        $user = User::factory()->create();
        /** @var Account $glAccount */
        $glAccount = Account::query()->where('is_active', true)->firstOrFail();

        /** @var CashAccountService $service */
        $service = app(CashAccountService::class);

        $cash = $service->create([
            'code' => 'CASH-UPD-001',
            'name' => ['en' => 'Cash Before', 'ar' => 'نقدية قبل'],
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
        ], $user->id);

        $updated = $service->update($cash->id, [
            'name' => ['en' => 'Cash After', 'ar' => 'نقدية بعد'],
            'is_active' => false,
        ], $cash->lock_version, $user->id);

        $this->assertFalse($updated->is_active);
        $this->assertSame('Cash After', $updated->getTranslation('name', 'en'));
        $this->assertSame(1, $updated->lock_version);
    }

    public function test_cash_account_rejects_inactive_gl_account(): void
    {
        $user = User::factory()->create();
        $inactiveGlAccount = Account::query()->create([
            'code' => 'INACTIVE-GL-999',
            'name' => ['en' => 'Inactive Account', 'ar' => 'حساب غير مفعّل'],
            'type' => 'asset',
            'nature' => 'debit',
            'currency' => 'EGP',
            'is_active' => false,
        ]);

        /** @var CashAccountService $service */
        $service = app(CashAccountService::class);

        $this->expectException(ValidationException::class);
        $service->create([
            'code' => 'CASH-INVALID',
            'name' => ['en' => 'Invalid Safe', 'ar' => 'خزينة غير صالحة'],
            'gl_account_id' => $inactiveGlAccount->id,
            'currency' => 'EGP',
        ], $user->id);
    }

    public function test_bank_account_validates_gl_account_currency_and_audit(): void
    {
        $user = User::factory()->create();
        /** @var Account $glAccount */
        $glAccount = Account::query()->where('is_active', true)->firstOrFail();

        /** @var BankAccountService $service */
        $service = app(BankAccountService::class);

        $bank = $service->create([
            'code' => 'BANK-001',
            'name' => ['en' => 'Operating Account', 'ar' => 'حساب التشغيل'],
            'bank_name' => ['en' => 'CIB Bank', 'ar' => 'البنك التجاري الدولي'],
            'account_number' => '100020003000',
            'iban' => 'EG99000100020003000',
            'swift' => 'CIBEGCX',
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
        ], $user->id);

        $this->assertDatabaseHas('bank_account', [
            'id' => $bank->id,
            'code' => 'BANK-001',
            'account_number' => '100020003000',
            'iban' => 'EG99000100020003000',
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
        ]);

        // Model relationship check
        $this->assertEquals($glAccount->id, $bank->glAccount->id);
        $this->assertEquals('EGP', $bank->currencyRef->code);

        // Assert audit
        $activity = Activity::query()
            ->where('properties->entity_type', 'bank_account')
            ->where('properties->entity_id', $bank->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('create', $activity->event);
    }

    public function test_bank_account_update_can_deactivate_and_clear_nullable_bank_fields(): void
    {
        $user = User::factory()->create();
        /** @var Account $glAccount */
        $glAccount = Account::query()->where('is_active', true)->firstOrFail();

        /** @var BankAccountService $service */
        $service = app(BankAccountService::class);

        $bank = $service->create([
            'code' => 'BANK-UPD-001',
            'name' => ['en' => 'Bank Before', 'ar' => 'بنك قبل'],
            'bank_name' => ['en' => 'Bank Name', 'ar' => 'اسم البنك'],
            'account_number' => '123',
            'iban' => 'EG123',
            'swift' => 'SWIFT',
            'gl_account_id' => $glAccount->id,
            'currency' => 'EGP',
        ], $user->id);

        $updated = $service->update($bank->id, [
            'name' => ['en' => 'Bank After', 'ar' => 'بنك بعد'],
            'bank_name' => null,
            'account_number' => null,
            'iban' => null,
            'swift' => null,
            'is_active' => false,
        ], $bank->lock_version, $user->id);

        $this->assertFalse($updated->is_active);
        $this->assertSame('Bank After', $updated->getTranslation('name', 'en'));
        $this->assertNull($updated->getRawOriginal('bank_name'));
        $this->assertNull($updated->account_number);
        $this->assertNull($updated->iban);
        $this->assertNull($updated->swift);
        $this->assertSame(1, $updated->lock_version);
    }

    public function test_slice1_permissions_are_registered(): void
    {
        $permissions = [
            'customers.view',
            'customers.create',
            'customers.edit',
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'cash.view',
            'cash.create',
            'cash.edit',
            'banks.view',
            'banks.create',
            'banks.edit',
        ];

        $this->assertSame(
            count($permissions),
            Permission::query()->whereIn('name', $permissions)->count()
        );
    }

    public function test_attachment_registry_accepts_slice1_entities(): void
    {
        /** @var AttachmentEntityAuthorizer $authorizer */
        $authorizer = app(AttachmentEntityAuthorizer::class);
        $allowedTypes = $authorizer->allowedEntityTypes();

        $this->assertContains('customer', $allowedTypes);
        $this->assertContains('supplier', $allowedTypes);
        $this->assertContains('cash_account', $allowedTypes);
        $this->assertContains('bank_account', $allowedTypes);
    }
}
