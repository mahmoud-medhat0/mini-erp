<?php

namespace Tests\Feature;

use App\Models\AccountCategory;
use App\Models\AccountType;
use App\Models\User;
use Database\Seeders\AccountCategorySeeder;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(AccountCategorySeeder::class);
        $this->seed(AccountTypeSeeder::class);

        $this->user = User::factory()->create();

        $viewPerm = Permission::firstOrCreate(['name' => 'accounting.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'accounting.create', 'guard_name' => 'web']);
        $deletePerm = Permission::firstOrCreate(['name' => 'accounting.delete', 'guard_name' => 'web']);
        $accTypesPerm = Permission::firstOrCreate(['name' => 'accounting.account_types', 'guard_name' => 'web']);
        $accCatPerm = Permission::firstOrCreate(['name' => 'accounting.account_categories', 'guard_name' => 'web']);

        $this->user->givePermissionTo([$viewPerm, $createPerm, $deletePerm, $accTypesPerm, $accCatPerm]);
    }

    public function test_account_category_table_exists_and_system_categories_are_seeded(): void
    {
        $this->assertTrue(Schema::hasTable('account_category'));
        $this->assertGreaterThanOrEqual(8, AccountCategory::count());

        $asset = AccountCategory::where('code', 'ASSET')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('debit', $asset->normal_balance);
        $this->assertEquals('balance_sheet', $asset->statement_type);
        $this->assertFalse($asset->is_contra);
        $this->assertTrue($asset->is_system);
    }

    public function test_relationships_between_account_category_and_account_type(): void
    {
        $assetCategory = AccountCategory::where('code', 'ASSET')->firstOrFail();
        $this->assertGreaterThan(0, $assetCategory->accountTypes()->count());

        $currentAssetType = AccountType::where('code', 'ASSET_CURRENT')->firstOrFail();
        $this->assertNotNull($currentAssetType->accountCategory);
        $this->assertEquals($assetCategory->id, $currentAssetType->account_category_id);
    }

    public function test_account_type_creation_requires_valid_account_category_id(): void
    {
        $response = $this->actingAs($this->user)->post('/accounting/account-types', [
            'code' => 'CUSTOM_TYPE_INVALID',
            'name_en' => 'Invalid Custom Type',
            'name_ar' => 'نوع مخصص غير صالح',
            'account_category_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertSessionHasErrors(['account_category_id']);
    }

    public function test_account_type_creation_syncs_legacy_category_string_and_defaults(): void
    {
        $revenueCategory = AccountCategory::where('code', 'REVENUE')->firstOrFail();

        $response = $this->actingAs($this->user)->post('/accounting/account-types', [
            'code' => 'REVENUE_SUBSCRIPTION',
            'name_en' => 'Subscription Revenue',
            'name_ar' => 'إيرادات الاشتراكات',
            'account_category_id' => $revenueCategory->id,
        ]);

        $response->assertRedirect();

        $createdType = AccountType::where('code', 'REVENUE_SUBSCRIPTION')->firstOrFail();
        $this->assertEquals($revenueCategory->id, $createdType->account_category_id);
        $this->assertEquals('revenue', $createdType->category);
        $this->assertEquals('credit', $createdType->normal_balance);
        $this->assertEquals('income_statement', $createdType->statement_type);
        $this->assertFalse($createdType->is_contra);
    }

    public function test_system_account_categories_cannot_be_deleted(): void
    {
        $systemCategory = AccountCategory::where('code', 'ASSET')->firstOrFail();

        $response = $this->actingAs($this->user)->delete("/accounting/account-categories/{$systemCategory->id}");

        $response->assertSessionHasErrors(['account_category']);
        $this->assertDatabaseHas('account_category', ['id' => $systemCategory->id]);
    }

    public function test_used_account_categories_cannot_be_deleted(): void
    {
        $customCategory = AccountCategory::create([
            'code' => 'CUSTOM_USED_CAT',
            'name' => ['en' => 'Custom Used Category', 'ar' => 'تصنيف مخصص مستخدم'],
            'normal_balance' => 'debit',
            'statement_type' => 'balance_sheet',
            'is_contra' => false,
            'sort_order' => 99,
            'is_system' => false,
            'is_active' => true,
        ]);

        AccountType::create([
            'account_category_id' => $customCategory->id,
            'code' => 'CUSTOM_TYPE_LINKED',
            'name' => ['en' => 'Linked Type', 'ar' => 'نوع مرتبط'],
            'normal_balance' => 'debit',
            'statement_type' => 'balance_sheet',
            'category' => 'custom_used_cat',
            'is_contra' => false,
            'is_system' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->delete("/accounting/account-categories/{$customCategory->id}");

        $response->assertSessionHasErrors(['account_category']);
        $this->assertDatabaseHas('account_category', ['id' => $customCategory->id]);
    }

    public function test_unused_custom_account_category_can_be_deleted(): void
    {
        $customCategory = AccountCategory::create([
            'code' => 'CUSTOM_UNUSED_CAT',
            'name' => ['en' => 'Custom Unused Category', 'ar' => 'تصنيف مخصص غير مستخدم'],
            'normal_balance' => 'credit',
            'statement_type' => 'income_statement',
            'is_contra' => false,
            'sort_order' => 100,
            'is_system' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->delete("/accounting/account-categories/{$customCategory->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('account_category', ['id' => $customCategory->id]);
    }

    public function test_account_categories_page_renders_via_inertia_with_counts(): void
    {
        $response = $this->actingAs($this->user)->get('/accounting/account-categories');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/AccountCategories')
            ->has('accountCategories')
            ->where('accountCategories.0.code', 'ASSET')
            ->has('accountCategories.0.account_types_count')
        );
    }

    public function test_account_types_page_receives_account_categories(): void
    {
        $response = $this->actingAs($this->user)->get('/accounting/account-types');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/AccountTypes')
            ->has('accountTypes')
            ->has('accountCategories')
        );
    }

    public function test_assert_no_domain_account_category_class_exists(): void
    {
        $this->assertFalse(class_exists('App\\Domain\\Accounting\\AccountCategory'));
    }

    public function test_assert_no_company_or_branch_or_tenant_scopes_in_account_category_table(): void
    {
        $columns = Schema::getColumnListing('account_category');

        $this->assertNotContains('company_id', $columns);
        $this->assertNotContains('branch_id', $columns);
        $this->assertNotContains('tenant_id', $columns);
    }
}
