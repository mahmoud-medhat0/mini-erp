<?php

namespace Tests\Integration;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_m3_foundation_tables_exist_without_replacing_users(): void
    {
        foreach ([
            'users',
            'company',
            'branch',
            'company_user',
            'currency',
            'exchange_rate',
            'fiscal_year',
            'financial_period',
            'number_sequence',
            'audit_log',
            'attachment',
            'notification',
            'roles',
            'permissions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertFalse(Schema::hasTable('app_user'));
        $this->assertTrue(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasColumn('roles', 'company_id'));
        $this->assertTrue(Schema::hasColumn('permissions', 'module'));
        $this->assertTrue(Schema::hasColumn('role_has_permissions', 'scope_json'));
    }

    public function test_branch_codes_are_unique_per_company(): void
    {
        $companyId = (string) Str::uuid();

        Company::query()->create([
            'id' => $companyId,
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
        ]);

        Branch::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'code' => 'MAIN',
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        ]);

        $this->expectException(QueryException::class);

        Branch::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'code' => 'MAIN',
            'name' => ['en' => 'Duplicate Branch', 'ar' => 'فرع مكرر'],
        ]);
    }

    public function test_company_membership_uses_the_native_users_table(): void
    {
        $user = User::factory()->create();
        $companyId = (string) Str::uuid();

        Company::query()->create([
            'id' => $companyId,
            'name' => ['en' => 'Business Company', 'ar' => 'شركة أعمال'],
        ]);

        DB::table('company_user')->insert([
            'company_id' => $companyId,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $companyId,
            'user_id' => $user->id,
        ]);
    }

    public function test_foundation_names_are_translatable(): void
    {
        $currency = Currency::query()->create([
            'code' => 'EGP',
            'name' => ['en' => 'Egyptian Pound', 'ar' => 'الجنيه المصري'],
            'symbol' => 'E£',
        ]);

        $company = Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => ['en' => 'Demo Company', 'ar' => 'شركة تجريبية'],
        ]);

        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        ]);

        foreach ([$currency, $company, $branch] as $model) {
            $this->assertSame(['name'], $model->getTranslatableAttributes());
            $this->assertNotSame('', $model->getTranslation('name', 'en'));
            $this->assertNotSame('', $model->getTranslation('name', 'ar'));
        }
    }
}
