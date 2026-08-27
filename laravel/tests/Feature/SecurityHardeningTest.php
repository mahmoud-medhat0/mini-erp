<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('security.headers.enabled', true);
        config()->set('security.headers.content_security_policy.enabled', false);

        $this->seed(PermissionSeeder::class);
    }

    public function test_web_responses_include_baseline_security_headers(): void
    {
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_inactive_authenticated_user_is_logged_out_before_accessing_protected_pages(): void
    {
        $this->withoutVite();

        $user = User::factory()->create(['is_active' => false]);
        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_dashboard_requires_explicit_dashboard_view_permission(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();

        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_audit_log_requires_explicit_audit_view_permission(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->get('/audit-log')
            ->assertForbidden();

        $user->givePermissionTo('audit.view');

        $this->actingAs($user)
            ->get('/audit-log')
            ->assertOk();
    }

    public function test_tax_filing_permission_is_seeded_as_sensitive_capability(): void
    {
        $this->assertTrue(Permission::query()->where('name', 'taxes.file')->exists());

        $superAdmin = Role::query()->where('name', 'SUPER_ADMIN')->firstOrFail();
        $accountant = Role::query()->where('name', 'ACCOUNTANT')->firstOrFail();

        $this->assertTrue($superAdmin->hasPermissionTo('taxes.file'));
        $this->assertFalse($accountant->hasPermissionTo('taxes.file'));
    }

    public function test_authenticated_application_routes_have_explicit_authorization_or_documented_entity_authorizer(): void
    {
        $allowedAuthOnlyNames = [
            'foundation',
            'logout',
            'notifications',
            'notifications.read_all',
            'notifications.read',
            'attachments.index',
            'attachments.store',
            'attachments.show',
            'attachments.destroy',
        ];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (! in_array('auth', $middleware, true)) {
                continue;
            }

            $name = $route->getName();

            if ($name && in_array($name, $allowedAuthOnlyNames, true)) {
                continue;
            }

            $hasAuthorizationMiddleware = collect($middleware)->contains(
                static fn (string $entry): bool => Str::startsWith($entry, ['can:', 'permission.any:', 'permission.all:']),
            );

            $this->assertTrue(
                $hasAuthorizationMiddleware,
                sprintf('Authenticated route [%s] %s is missing explicit authorization middleware.', $name ?? 'unnamed', $route->uri()),
            );
        }
    }
}
