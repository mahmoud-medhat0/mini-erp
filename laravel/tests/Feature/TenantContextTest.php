<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

use function setPermissionsTeamId;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_session_tenant_is_replaced_with_the_users_first_company(): void
    {
        $this->withoutVite();

        [$user, $company, $branch] = $this->tenantUser();

        $this->actingAs($user)
            ->withSession([
                'active_company_id' => (string) Str::uuid(),
                'active_branch_id' => (string) Str::uuid(),
            ])
            ->get('/')
            ->assertOk()
            ->assertSessionHas('active_company_id', $company->id)
            ->assertSessionHas('active_branch_id', $branch->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.company.id', $company->id)
                ->where('tenant.branch.id', $branch->id)
                ->etc());
    }

    public function test_inertia_shared_permissions_are_resolved_for_the_active_company(): void
    {
        $this->withoutVite();
        $this->seed(RbacSeeder::class);

        [$user, $company] = $this->tenantUser();

        $role = Role::query()
            ->where('company_id', $company->id)
            ->where('name', 'VIEWER')
            ->first();

        if (! $role instanceof Role) {
            $template = Role::query()
                ->whereNull('company_id')
                ->where('name', 'VIEWER')
                ->with('permissions')
                ->firstOrFail();

            $role = Role::query()->create([
                'company_id' => $company->id,
                'name' => 'VIEWER',
                'guard_name' => 'web',
                'is_template' => false,
            ]);
            $role->permissions()->sync($template->permissions->pluck('id')->all());
        }

        setPermissionsTeamId($company->id);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.permissions')
                ->where('auth.permissions.0', 'dashboard.view')
                ->where('auth.permissions.1', 'reports.view')
                ->etc());
    }

    /**
     * @return array{User, Company, Branch}
     */
    private function tenantUser(): array
    {
        $user = User::factory()->create();
        $company = Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => ['en' => 'Tenant Company', 'ar' => 'شركة مستأجرة'],
        ]);
        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return [$user, $company, $branch];
    }
}
