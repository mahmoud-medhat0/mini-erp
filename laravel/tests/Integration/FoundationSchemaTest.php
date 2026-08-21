<?php

namespace Tests\Integration;

use App\Models\Branch;
use App\Models\Company;
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
        $this->assertTrue(Schema::hasColumn('roles', 'company_id'));
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
            'name' => ['en' => 'Tenant Company', 'ar' => 'شركة مستأجرة'],
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
}
