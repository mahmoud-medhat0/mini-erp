<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

use function setPermissionsTeamId;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_without_a_company_are_redirected_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/onboarding');
    }

    public function test_onboarding_page_renders_for_users_without_a_company(): void
    {
        $this->withoutVite();
        $this->seed(CurrencySeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/onboarding')
            ->assertOk()
            ->assertSee('Onboarding\/Create', false)
            ->assertSee('currencies', false);
    }

    public function test_first_run_onboarding_creates_the_tenant_and_owner_rbac_scope(): void
    {
        $this->seed([CurrencySeeder::class, RbacSeeder::class]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding', [
                'company' => [
                    'name' => [
                        'en' => 'Acme Construction',
                        'ar' => 'اكمي للمقاولات',
                    ],
                    'base_currency' => 'EGP',
                ],
                'branch' => [
                    'code' => 'main',
                    'name' => [
                        'en' => 'Main Branch',
                        'ar' => 'الفرع الرئيسي',
                    ],
                ],
            ])
            ->assertRedirect('/')
            ->assertSessionHas('active_company_id')
            ->assertSessionHas('active_branch_id');

        $company = DB::table('company')->where('base_currency', 'EGP')->first();
        $branch = DB::table('branch')->where('company_id', $company->id)->first();
        $companyAdmin = DB::table('roles')
            ->where('company_id', $company->id)
            ->where('name', 'COMPANY_ADMIN')
            ->first();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame('MAIN', $branch->code);
        $this->assertSame(9, DB::table('roles')->where('company_id', $company->id)->where('is_template', false)->count());
        $this->assertGreaterThan(0, DB::table('role_has_permissions')->where('role_id', $companyAdmin->id)->count());

        $this->assertDatabaseHas('model_has_roles', [
            'company_id' => $company->id,
            'role_id' => $companyAdmin->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);

        setPermissionsTeamId($company->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->fresh()->hasRole('COMPANY_ADMIN'));
        $this->assertTrue($user->fresh()->can('dashboard.view'));
    }
}
