<?php

namespace Tests\Integration;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertFalse(Schema::hasTable('company_user'));
        $this->assertTrue(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasColumn('roles', 'company_id'));
        $this->assertTrue(Schema::hasColumn('permissions', 'module'));
        $this->assertTrue(Schema::hasColumn('role_has_permissions', 'scope_json'));
        $this->assertFalse(Schema::hasColumn('branch', 'company_id'));
        $this->assertFalse(Schema::hasColumn('number_sequence', 'company_id'));
        $this->assertFalse(Schema::hasColumn('number_sequence', 'include_branch'));
        $this->assertFalse(Schema::hasColumn('audit_log', 'company_id'));
        $this->assertFalse(Schema::hasColumn('audit_log', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('attachment', 'company_id'));
        $this->assertFalse(Schema::hasColumn('notification', 'company_id'));
    }

    public function test_branch_records_do_not_assume_company_ownership(): void
    {
        Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MAIN',
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        ]);

        Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MAIN',
            'name' => ['en' => 'Duplicate Branch', 'ar' => 'فرع مكرر'],
        ]);

        $this->assertSame(2, Branch::query()->where('code', 'MAIN')->count());
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
