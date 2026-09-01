<?php

namespace Tests\Integration;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
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
        $this->assertFalse(Schema::hasColumn('fiscal_year', 'company_id'));
        $this->assertFalse(Schema::hasColumn('number_sequence', 'company_id'));
        $this->assertFalse(Schema::hasColumn('number_sequence', 'include_branch'));
        $this->assertFalse(Schema::hasColumn('audit_log', 'company_id'));
        $this->assertFalse(Schema::hasColumn('audit_log', 'branch_id'));
        $this->assertFalse(Schema::hasColumn('attachment', 'company_id'));
        $this->assertFalse(Schema::hasColumn('notification', 'company_id'));
    }

    public function test_branch_codes_are_globally_unique_without_company_ownership(): void
    {
        Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MAIN',
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        ]);

        $this->expectException(QueryException::class);

        Branch::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'MAIN',
            'name' => ['en' => 'Duplicate Branch', 'ar' => 'فرع مكرر'],
        ]);
    }

    public function test_fiscal_years_are_global_and_financial_periods_belong_to_fiscal_years(): void
    {
        $fiscalYearId = (string) Str::uuid();

        DB::table('fiscal_year')->insert([
            'id' => $fiscalYearId,
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);

        DB::table('financial_period')->insert([
            'id' => (string) Str::uuid(),
            'fiscal_year_id' => $fiscalYearId,
            'month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('financial_period', [
            'fiscal_year_id' => $fiscalYearId,
            'month' => 1,
        ]);

        $this->expectException(QueryException::class);

        DB::table('fiscal_year')->insert([
            'id' => (string) Str::uuid(),
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
        ]);
    }

    public function test_foundation_names_are_translatable(): void
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'EGP'],
            [
                'name' => ['en' => 'Egyptian Pound', 'ar' => 'الجنيه المصري'],
                'symbol' => 'E£',
            ]
        );

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
