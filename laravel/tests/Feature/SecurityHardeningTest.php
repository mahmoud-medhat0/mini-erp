<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireSensitiveActionConfirmation;
use App\Models\User;
use App\Support\Security\RouteAuthorizationAuditor;
use App\Support\Security\SensitiveActionRegistry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
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
        $allowlist = RouteAuthorizationAuditor::allowlist();

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (! in_array('auth', $middleware, true)) {
                continue;
            }

            $name = $route->getName();

            if ($name && array_key_exists($name, $allowlist)) {
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

    public function test_security_route_audit_command_succeeds_on_current_route_table(): void
    {
        $this->artisan('security:route-audit')
            ->assertSuccessful()
            ->expectsOutputToContain('Total routes scanned:')
            ->expectsOutputToContain('All protected routes satisfy authorization requirements.');
    }

    public function test_security_route_audit_command_strict_succeeds_on_current_route_table(): void
    {
        $this->artisan('security:route-audit', ['--strict' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('All protected routes satisfy authorization requirements.');
    }

    public function test_security_route_audit_command_json_returns_valid_json_with_expected_keys(): void
    {
        $exitCode = Artisan::call('security:route-audit', ['--json' => true]);
        $output = trim(Artisan::output());

        $this->assertSame(0, $exitCode);

        $data = json_decode($output, true);
        $this->assertIsArray($data, 'Command output must be valid JSON: '.$output);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('counts', $data);
        $this->assertArrayHasKey('failures', $data);
        $this->assertArrayHasKey('allowlisted', $data);
        $this->assertArrayHasKey('public_allowlisted', $data);

        $this->assertIsInt($data['total']);
        $this->assertGreaterThan(0, $data['total']);
        $this->assertSame(0, $data['counts']['failing']);
        $this->assertGreaterThan(0, $data['counts']['explicitly_authorized']);
        $this->assertEmpty($data['failures']);
        $this->assertNotEmpty($data['allowlisted']);
        $this->assertNotEmpty($data['public_allowlisted']);

        foreach ($data['allowlisted'] as $entry) {
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('uri', $entry);
            $this->assertArrayHasKey('methods', $entry);
            $this->assertArrayHasKey('reason', $entry);
            $this->assertIsString($entry['reason']);
            $this->assertNotEmpty($entry['reason']);
        }

        foreach ($data['public_allowlisted'] as $entry) {
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('uri', $entry);
            $this->assertArrayHasKey('methods', $entry);
            $this->assertArrayHasKey('reason', $entry);
            $this->assertIsString($entry['reason']);
            $this->assertNotEmpty($entry['reason']);
        }
    }

    public function test_dynamically_registered_auth_only_route_without_authorization_is_classified_as_failing(): void
    {
        Route::get('/_test_unauthorized_auth_route', static fn () => 'unauthorized')
            ->middleware('auth')
            ->name('test.dynamic.unauthorized');

        $auditor = app(RouteAuthorizationAuditor::class);
        $result = $auditor->audit();

        $this->assertGreaterThan(0, $result['counts']['failing']);
        $failingNames = array_column($result['failures'], 'name');
        $this->assertContains('test.dynamic.unauthorized', $failingNames);
    }

    public function test_strict_mode_returns_exit_code_1_when_dynamic_failing_route_exists(): void
    {
        Route::get('/_test_strict_failing_route', static fn () => 'unauthorized')
            ->middleware('auth')
            ->name('test.dynamic.strict_failing');

        $this->artisan('security:route-audit', ['--strict' => true])
            ->assertExitCode(1);

        $exitCode = Artisan::call('security:route-audit', ['--strict' => true, '--json' => true]);
        $this->assertSame(1, $exitCode);

        $output = trim(Artisan::output());
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, $data['counts']['failing']);
        $failingNames = array_column($data['failures'], 'name');
        $this->assertContains('test.dynamic.strict_failing', $failingNames);
    }

    public function test_public_route_without_public_allowlist_is_classified_as_failing(): void
    {
        Route::get('/_test_unlisted_public_route', static fn () => 'unlisted')
            ->name('test.dynamic.unlisted_public');

        $auditor = app(RouteAuthorizationAuditor::class);
        $result = $auditor->audit();

        $this->assertGreaterThan(0, $result['counts']['failing']);
        $failingNames = array_column($result['failures'], 'name');
        $this->assertContains('test.dynamic.unlisted_public', $failingNames);

        $this->artisan('security:route-audit', ['--strict' => true])
            ->assertExitCode(1);
    }

    public function test_all_service_authorized_allowlist_route_names_are_documented_with_non_empty_reason_strings(): void
    {
        $allowlist = RouteAuthorizationAuditor::allowlist();

        $requiredKeys = [
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

        foreach ($requiredKeys as $requiredKey) {
            $this->assertArrayHasKey($requiredKey, $allowlist);
        }

        foreach ($allowlist as $name => $reason) {
            $this->assertIsString($name);
            $this->assertNotEmpty($name);
            $this->assertIsString($reason);
            $this->assertNotEmpty(trim($reason));
        }
    }

    public function test_all_public_allowlist_entries_are_documented_with_non_empty_reason_strings(): void
    {
        $allowlist = RouteAuthorizationAuditor::publicAllowlist();

        $requiredKeys = [
            'name:health',
            'name:locale.update',
            'uri:up',
        ];

        foreach ($requiredKeys as $requiredKey) {
            $this->assertArrayHasKey($requiredKey, $allowlist);
        }

        foreach ($allowlist as $key => $reason) {
            $this->assertIsString($key);
            $this->assertNotEmpty($key);
            $this->assertIsString($reason);
            $this->assertNotEmpty(trim($reason));
        }
    }

    public function test_no_tenant_company_or_branch_security_scope_introduced(): void
    {
        $auditor = app(RouteAuthorizationAuditor::class);
        $result = $auditor->audit();

        $this->assertSame(0, $result['counts']['failing']);
        $this->assertGreaterThan(0, $result['total']);
    }

    public function test_creating_user_with_password_shorter_than_configured_min_length_is_rejected(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Test User',
                'email' => 'test-short@example.com',
                'password' => 'Pass1!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test-short@example.com']);
    }

    public function test_creating_user_without_letters_is_rejected_when_letters_policy_is_true(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Test User',
                'email' => 'test-no-letters@example.com',
                'password' => '123456789012!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test-no-letters@example.com']);
    }

    public function test_creating_user_without_numbers_is_rejected_when_numbers_policy_is_true(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Test User',
                'email' => 'test-no-numbers@example.com',
                'password' => 'PasswordWithoutNumbers!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test-no-numbers@example.com']);
    }

    public function test_creating_user_without_symbols_is_rejected_when_symbols_policy_is_true(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Test User',
                'email' => 'test-no-symbols@example.com',
                'password' => 'Password123456',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test-no-symbols@example.com']);
    }

    public function test_creating_user_without_mixed_case_is_rejected_when_mixed_case_policy_is_true(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Test User',
                'email' => 'test-no-mixed-case@example.com',
                'password' => 'password12345!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test-no-mixed-case@example.com']);
    }

    public function test_creating_user_with_strong_default_compliant_password_succeeds(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Strong User',
                'email' => 'strong-user@example.com',
                'password' => 'ValidPass123!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'strong-user@example.com')->firstOrFail();
        $this->assertSame('Strong User', $user->name);
        $this->assertTrue(Hash::check('ValidPass123!', $user->password));
    }

    public function test_creating_user_with_password_longer_than_configured_max_length_is_rejected(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Too Long Password',
                'email' => 'too-long-password@example.com',
                'password' => str_repeat('A', 129).'a1!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'too-long-password@example.com']);
    }

    public function test_password_hashes_are_never_stored_as_plaintext(): void
    {
        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Plaintext Check User',
                'email' => 'plaintext-check@example.com',
                'password' => 'SuperSecret123!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'plaintext-check@example.com')->firstOrFail();
        $this->assertNotSame('SuperSecret123!', $user->password);
        $this->assertTrue(Hash::check('SuperSecret123!', $user->password));
        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $user->password);
    }

    public function test_updating_user_with_empty_password_preserves_existing_password_hash(): void
    {
        $manager = $this->managementUser();
        $target = User::factory()->create([
            'email' => 'target-user@example.com',
            'password' => Hash::make('InitialStrongPass123!'),
        ]);
        $initialHash = $target->password;

        $this->actingAs($manager)
            ->patch("/settings/users/{$target->id}", [
                'name' => 'Updated Name',
                'email' => 'target-user@example.com',
                'password' => '',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $target->fresh();
        $this->assertSame('Updated Name', $fresh->name);
        $this->assertSame($initialHash, $fresh->password);
    }

    public function test_updating_user_without_is_active_field_preserves_existing_active_state(): void
    {
        $manager = $this->managementUser();
        $target = User::factory()->create([
            'email' => 'inactive-target@example.com',
            'password' => Hash::make('InitialStrongPass123!'),
            'is_active' => false,
        ]);

        $this->actingAs($manager)
            ->patch("/settings/users/{$target->id}", [
                'name' => 'Inactive Updated',
                'email' => 'inactive-target@example.com',
                'password' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $target->fresh();
        $this->assertSame('Inactive Updated', $fresh->name);
        $this->assertFalse($fresh->is_active);
    }

    public function test_updating_user_with_weak_provided_password_is_rejected(): void
    {
        $manager = $this->managementUser();
        $target = User::factory()->create([
            'email' => 'target-weak@example.com',
            'password' => Hash::make('InitialStrongPass123!'),
        ]);
        $initialHash = $target->password;

        $this->actingAs($manager)
            ->patch("/settings/users/{$target->id}", [
                'name' => 'Updated Name',
                'email' => 'target-weak@example.com',
                'password' => 'weak',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $fresh = $target->fresh();
        $this->assertSame($initialHash, $fresh->password);
    }

    public function test_updating_user_with_strong_provided_password_changes_password_hash(): void
    {
        $manager = $this->managementUser();
        $target = User::factory()->create([
            'email' => 'target-strong@example.com',
            'password' => Hash::make('InitialStrongPass123!'),
        ]);
        $initialHash = $target->password;

        $this->actingAs($manager)
            ->patch("/settings/users/{$target->id}", [
                'name' => 'Updated Strong',
                'email' => 'target-strong@example.com',
                'password' => 'BrandNewStrongPass456!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $target->fresh();
        $this->assertNotSame($initialHash, $fresh->password);
        $this->assertTrue(Hash::check('BrandNewStrongPass456!', $fresh->password));
    }

    public function test_password_policy_respects_custom_configuration(): void
    {
        config()->set('security.password_policy.min_length', 16);

        $manager = $this->managementUser();

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Thirteen Chars',
                'email' => 'thirteen@example.com',
                'password' => 'Short1234567!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->actingAs($manager)
            ->post('/settings/users', [
                'name' => 'Sixteen Chars',
                'email' => 'sixteen@example.com',
                'password' => 'LongEnoughPass123!',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'sixteen@example.com']);
    }

    public function test_unauthorized_user_cannot_create_or_update_user_settings(): void
    {
        $unauthorized = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create(['is_active' => true]);

        $this->actingAs($unauthorized)
            ->post('/settings/users', [
                'name' => 'Unauthorized Post',
                'email' => 'unauth@example.com',
                'password' => 'ValidPass123!',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->patch("/settings/users/{$target->id}", [
                'name' => 'Unauthorized Patch',
                'email' => 'target-patched@example.com',
                'password' => 'ValidPass123!',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_sensitive_action_registry_contains_all_thirty_eight_required_routes(): void
    {
        $all = SensitiveActionRegistry::all();
        $this->assertCount(38, $all);

        $reasonRequiredCount = 0;
        $standardCount = 0;

        foreach ($all as $routeName => $config) {
            $this->assertIsString($routeName);
            $this->assertNotEmpty($config['confirmation_code'], "Confirmation code for {$routeName} must not be empty.");
            $this->assertIsBool($config['reason_required'], "reason_required for {$routeName} must be a boolean.");
            $this->assertNotEmpty($config['description'], "description for {$routeName} must not be empty.");

            if ($config['reason_required']) {
                $reasonRequiredCount++;
            } else {
                $standardCount++;
            }
        }

        $this->assertSame(21, $reasonRequiredCount, 'Exactly 21 sensitive routes must require a justification reason.');
        $this->assertSame(17, $standardCount, 'Exactly 17 sensitive routes must be standard confirmation routes.');
    }

    public function test_all_registered_sensitive_routes_have_confirmation_middleware_attached(): void
    {
        $routes = SensitiveActionRegistry::routes();
        $routeCollection = Route::getRoutes();

        foreach ($routes as $routeName) {
            $route = $routeCollection->getByName($routeName);
            $this->assertNotNull($route, "Registered sensitive route [{$routeName}] must exist in application routing table.");

            $middleware = $route->gatherMiddleware();
            $hasSensitiveMiddleware = in_array('sensitive.confirm', $middleware, true)
                || in_array(RequireSensitiveActionConfirmation::class, $middleware, true);

            $this->assertTrue(
                $hasSensitiveMiddleware,
                "Route [{$routeName}] must have 'sensitive.confirm' middleware attached."
            );
        }
    }

    public function test_sensitive_action_requires_explicit_confirmation_code_and_fails_closed(): void
    {
        $user = $this->managementUser();

        // 1. Missing confirm_action
        $response = $this->actingAs($user)
            ->postJson('/fixed-assets/asset-dummy-1/reverse-capitalization', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['confirm_action']);

        // 2. Wrong confirm_action
        $wrongResponse = $this->actingAs($user)
            ->postJson('/fixed-assets/asset-dummy-1/reverse-capitalization', [
                'confirm_action' => 'INVALID_ACTION_CODE',
                'reason' => 'Valid reason text for test',
            ]);

        $wrongResponse->assertStatus(422);
        $wrongResponse->assertJsonValidationErrors(['confirm_action']);
    }

    public function test_sensitive_action_requiring_reason_fails_when_reason_is_missing_or_too_short(): void
    {
        $user = $this->managementUser();

        // Missing reason for reason-required route
        $responseMissing = $this->actingAs($user)
            ->postJson('/fixed-assets/asset-dummy-1/reverse-capitalization', [
                'confirm_action' => 'REVERSE_FIXED_ASSET_CAPITALIZATION',
            ]);

        $responseMissing->assertStatus(422);
        $responseMissing->assertJsonValidationErrors(['reason']);

        // Too short reason (< 3 characters)
        $responseShort = $this->actingAs($user)
            ->postJson('/fixed-assets/asset-dummy-1/reverse-capitalization', [
                'confirm_action' => 'REVERSE_FIXED_ASSET_CAPITALIZATION',
                'reason' => 'ab',
            ]);

        $responseShort->assertStatus(422);
        $responseShort->assertJsonValidationErrors(['reason']);

        // Whitespace-only reason (< 3 trimmed characters)
        $responseWhitespace = $this->actingAs($user)
            ->postJson('/fixed-assets/asset-dummy-1/reverse-capitalization', [
                'confirm_action' => 'REVERSE_FIXED_ASSET_CAPITALIZATION',
                'reason' => '   ',
            ]);

        $responseWhitespace->assertStatus(422);
        $responseWhitespace->assertJsonValidationErrors(['reason']);
    }

    public function test_sensitive_action_confirmation_passes_and_logs_audit_trail_evidence(): void
    {
        $user = $this->managementUser();

        // Direct middleware invocation test with request simulation
        $request = Request::create('/accounting/journal/123/reverse', 'POST', [
            'confirm_action' => 'REVERSE_JOURNAL_ENTRY',
            'reason' => 'Manual reversal requested due to vendor credit adjustment',
        ], [], [], [
            'HTTP_USER_AGENT' => 'PHPUnit-Testing-Agent/1.0',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_REQUEST_ID' => 'test-req-uuid-1234',
        ]);

        $route = Route::getRoutes()->getByName('accounting.journal.reverse');
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        $middleware = new RequireSensitiveActionConfirmation;
        $executed = false;

        $response = $middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($executed, 'Middleware must allow valid sensitive confirmation request to proceed.');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('REVERSE_JOURNAL_ENTRY', $request->attributes->get('sensitive_action_code'));
        $this->assertSame('Manual reversal requested due to vendor credit adjustment', $request->attributes->get('sensitive_action_reason'));

        // Check Spatie Activitylog record
        $this->assertDatabaseHas('activity_log', [
            'event' => 'sensitive_action.confirmed',
            'description' => 'sensitive_action.confirmed',
            'causer_id' => $user->id,
            'causer_type' => User::class,
        ]);

        $latestLog = Activity::query()->where('event', 'sensitive_action.confirmed')->latest('id')->firstOrFail();
        $properties = $latestLog->properties->toArray();

        $this->assertSame('REVERSE_JOURNAL_ENTRY', $properties['sensitive_action_code']);
        $this->assertTrue($properties['sensitive_action_confirmed']);
        $this->assertSame('Manual reversal requested due to vendor credit adjustment', $properties['sensitive_action_reason']);
        $this->assertSame('accounting.journal.reverse', $properties['route_name']);
        $this->assertSame($user->id, $properties['actor_id']);
        $this->assertSame('test-req-uuid-1234', $properties['request_id']);
        $this->assertSame('127.0.0.1', $properties['ip']);
        $this->assertSame('PHPUnit-Testing-Agent/1.0', $properties['device']);
    }

    public function test_sensitive_action_allows_optional_reason_for_standard_confirmation_routes(): void
    {
        $user = $this->managementUser();

        $request = Request::create('/accounting/journal/123/post', 'POST', [
            'confirm_action' => 'POST_JOURNAL_ENTRY',
        ]);

        $route = Route::getRoutes()->getByName('accounting.journal.post');
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        $middleware = new RequireSensitiveActionConfirmation;
        $executed = false;

        $response = $middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($executed, 'Standard confirmation route must succeed without reason.');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('POST_JOURNAL_ENTRY', $request->attributes->get('sensitive_action_code'));
        $this->assertNull($request->attributes->get('sensitive_action_reason'));
    }

    public function test_unregistered_route_passes_through_sensitive_confirmation_middleware(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $route = Route::getRoutes()->getByName('dashboard');
        $request->setRouteResolver(fn () => $route);

        $middleware = new RequireSensitiveActionConfirmation;
        $executed = false;

        $response = $middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;

            return response()->json(['ok' => true]);
        });

        $this->assertTrue($executed);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_attachment_disk_configuration_remains_private_and_local_serving_is_disabled(): void
    {
        $disk = config('erp_attachments.disk');
        $this->assertSame('local', $disk);

        $serve = config("filesystems.disks.{$disk}.serve", false);
        $this->assertFalse((bool) $serve, 'Private attachment disk must not allow direct framework serving');

        $root = config("filesystems.disks.{$disk}.root");
        $this->assertSame(storage_path('app/private'), $root);
    }

    public function test_notification_and_attachment_endpoints_preserve_service_authorization_allowlist_classification(): void
    {
        $auditor = app(RouteAuthorizationAuditor::class);
        $result = $auditor->audit();

        $this->assertSame(0, $result['counts']['failing']);

        $allowlistedNames = array_column($result['allowlisted'], 'name');
        $this->assertContains('attachments.index', $allowlistedNames);
        $this->assertContains('attachments.store', $allowlistedNames);
        $this->assertContains('attachments.show', $allowlistedNames);
        $this->assertContains('attachments.destroy', $allowlistedNames);
        $this->assertContains('notifications', $allowlistedNames);
        $this->assertContains('notifications.read_all', $allowlistedNames);
        $this->assertContains('notifications.read', $allowlistedNames);
    }

    private function managementUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $superAdmin = Role::query()->where('name', 'SUPER_ADMIN')->firstOrFail();
        $user->assignRole($superAdmin);

        return $user;
    }
}
