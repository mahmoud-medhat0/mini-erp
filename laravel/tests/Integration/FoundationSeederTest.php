<?php

namespace Tests\Integration;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_and_rbac_seeders_build_the_foundation_catalogs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $expectedPermissionCount = collect(config('erp_rbac.modules'))
            ->sum(fn (array $actions): int => count($actions)) + count(config('erp_rbac.sensitive_capabilities'));

        $this->assertSame(6, DB::table('currency')->count());
        $this->assertDatabaseHas('currency', [
            'code' => 'KWD',
            'exponent' => 3,
        ]);
        $kwdName = json_decode(DB::table('currency')->where('code', 'KWD')->value('name'), true);
        $this->assertSame('Kuwaiti Dinar', $kwdName['en']);
        $this->assertSame('دينار كويتي', $kwdName['ar']);

        $this->assertSame($expectedPermissionCount, DB::table('permissions')->count());
        $this->assertDatabaseHas('permissions', [
            'name' => 'sales.post',
            'module' => 'sales',
            'action' => 'post',
        ]);
        $this->assertDatabaseHas('permissions', [
            'name' => 'view_financials',
            'module' => '_capability',
            'action' => 'view_financials',
        ]);

        $this->assertSame(9, DB::table('roles')->where('is_template', true)->count());

        $superAdmin = DB::table('roles')->where('name', 'SUPER_ADMIN')->first();
        $companyAdmin = DB::table('roles')->where('name', 'COMPANY_ADMIN')->first();
        $viewer = DB::table('roles')->where('name', 'VIEWER')->first();

        $this->assertSame($expectedPermissionCount, DB::table('role_has_permissions')->where('role_id', $superAdmin->id)->count());
        $this->assertSame($expectedPermissionCount - 1, DB::table('role_has_permissions')->where('role_id', $companyAdmin->id)->count());

        $viewerPermissions = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $viewer->id)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();

        $this->assertSame(['dashboard.view', 'reports.view'], $viewerPermissions);
    }
}
