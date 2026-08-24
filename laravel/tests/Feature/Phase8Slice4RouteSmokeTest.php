<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase8Slice4RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reports.view', 'view_financials', 'taxes.view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_public_login_page_loads(): void
    {
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->etc());
    }

    public function test_financial_user_can_reach_core_operational_pages(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'view_financials', 'taxes.view']);

        $this->actingAs($user);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->etc());

        $this->get('/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reports/Index')->etc());

        $this->get('/taxes/codes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Taxes/Codes/Index')->etc());

        $this->get('/reports/vat-register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reports/VatRegister')->etc());
    }

    public function test_user_without_report_permission_cannot_reach_financial_reports(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('taxes.view');

        $this->actingAs($user)
            ->get('/reports/vat-register')
            ->assertForbidden();
    }
}
