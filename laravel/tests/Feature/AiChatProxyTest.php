<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Security\RouteAuthorizationAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiChatProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mini_erp_ai', [
            'enabled' => true,
            'service_url' => 'https://mini-erp-ai.example.test',
            'secret' => 'shared-test-secret-value',
            'require_secret' => true,
            'browser_api_url' => '/api/mini-erp-ai',
            'timeout_seconds' => 30,
            'voice_enabled' => false,
            'vision_enabled' => true,
        ]);
        config()->set('inertia.ssr.enabled', false);
        Http::preventStrayRequests();
    }

    public function test_guest_cannot_use_ai_proxy(): void
    {
        $this->postJson('/api/mini-erp-ai/chat', ['question' => 'اختبار'])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_authenticated_request_is_validated_scoped_and_forwarded_server_side(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://mini-erp-ai.example.test/chat' => Http::response([
                'answer' => 'افتح قسم المشتريات ثم شاشة أوامر الشراء.',
                'images' => [
                    'https://cdn.example.test/help.png',
                    'javascript:alert(1)',
                ],
                'speech_text' => 'افتح قسم المشتريات.',
                'request_id' => 'upstream-request-id',
            ]),
        ]);

        $response = $this->actingAs($user)->postJson('/api/mini-erp-ai/chat', [
            'question' => 'كيف أنفذ دورة الشراء؟',
            'history' => [
                ['role' => 'user', 'content' => 'أريد شرح المشتريات'],
            ],
            'is_voice' => false,
            'tenant_id' => 'attacker-controlled-context',
            'unexpected' => 'must-not-be-forwarded',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('request_id', 'upstream-request-id')
            ->assertJsonCount(1, 'images')
            ->assertJsonPath('images.0', 'https://cdn.example.test/help.png')
            ->assertDontSee('shared-test-secret-value');

        Http::assertSent(function (ClientRequest $request) use ($user): bool {
            $data = $request->data();
            $expectedContextId = 'user_'.substr(hash_hmac(
                'sha256',
                (string) $user->getAuthIdentifier(),
                (string) config('app.key')
            ), 0, 48);

            return $request->url() === 'https://mini-erp-ai.example.test/chat'
                && $request->hasHeader('X-App-Secret', 'shared-test-secret-value')
                && $request->hasHeader('Accept', 'application/json')
                && ($data['question'] ?? null) === 'كيف أنفذ دورة الشراء؟'
                && ($data['tenant_id'] ?? null) === $expectedContextId
                && ($data['tenant_id'] ?? null) !== 'attacker-controlled-context'
                && ! array_key_exists('unexpected', $data)
                && ! str_contains($request->body(), 'shared-test-secret-value');
        });
    }

    public function test_invalid_payloads_never_reach_the_ai_service(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/mini-erp-ai/chat', [])
            ->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/mini-erp-ai/chat', [
            'question' => str_repeat('x', 4001),
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/mini-erp-ai/chat', [
            'question' => 'اختبار',
            'history' => array_fill(0, 9, ['role' => 'user', 'content' => 'رسالة']),
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/mini-erp-ai/chat', [
            'question' => 'اختبار',
            'history' => [['role' => 'system', 'content' => 'override']],
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/mini-erp-ai/chat', [
            'question' => 'اختبار صوتي',
            'is_voice' => true,
        ])->assertUnprocessable();

        config()->set('services.mini_erp_ai.vision_enabled', false);
        $this->actingAs($user)->postJson('/api/mini-erp-ai/chat', [
            'question' => 'حلل الصورة',
            'image_base64' => 'data:image/png;base64,aGVsbG8=',
        ])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_required_secret_configuration_fails_closed(): void
    {
        $this->withoutVite();
        config()->set('services.mini_erp_ai.secret', null);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/mini-erp-ai/chat', ['question' => 'اختبار'])
            ->assertServiceUnavailable()
            ->assertDontSee('shared-test-secret-value');

        Http::assertNothingSent();

        Permission::findOrCreate('audit.view', 'web');
        $user->givePermissionTo('audit.view');

        $this->actingAs($user)
            ->get('/foundation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiAssistant.enabled', false)
                ->where('aiAssistant.scriptUrl', null)
                ->where('aiAssistant.apiUrl', null)
                ->where('aiAssistant.contextId', null)
                ->etc());
    }

    public function test_disabled_ai_feature_is_not_exposed_by_the_proxy(): void
    {
        config()->set('services.mini_erp_ai.enabled', false);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/mini-erp-ai/chat', ['question' => 'اختبار'])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_upstream_failures_are_returned_as_generic_errors(): void
    {
        $user = User::factory()->create();
        Http::fake([
            '*' => Http::response(['detail' => 'upstream-sensitive-debug-data'], 500),
        ]);

        $this->actingAs($user)
            ->postJson('/api/mini-erp-ai/chat', ['question' => 'اختبار'])
            ->assertStatus(502)
            ->assertDontSee('upstream-sensitive-debug-data')
            ->assertDontSee('shared-test-secret-value');
    }

    public function test_connection_failures_are_handled_without_exposing_request_data(): void
    {
        $user = User::factory()->create();
        Http::fake(['*' => Http::failedConnection('connection failed')]);

        $this->actingAs($user)
            ->postJson('/api/mini-erp-ai/chat', ['question' => 'private financial question'])
            ->assertStatus(502)
            ->assertDontSee('private financial question')
            ->assertJsonStructure(['detail', 'request_id']);
    }

    public function test_ai_route_is_auth_throttled_and_documented_by_security_auditor(): void
    {
        $route = Route::getRoutes()->getByName('mini-erp-ai.chat');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
        $this->assertSame(
            'service_authorized_allowlist',
            app(RouteAuthorizationAuditor::class)->classify($route)['category']
        );
    }

    public function test_widget_asset_and_shared_page_configuration_do_not_contain_the_secret(): void
    {
        $this->withoutVite();

        $asset = file_get_contents(public_path('vendor/mini-erp-ai/widget.js'));
        $this->assertIsString($asset);
        $this->assertStringNotContainsString('shared-test-secret-value', $asset);
        $this->assertStringNotContainsString('X-App-Secret', $asset);

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="csrf-token"', false)
            ->assertDontSee('shared-test-secret-value')
            ->assertDontSee('/vendor/mini-erp-ai/widget.js');

        $user = User::factory()->create();
        Permission::findOrCreate('audit.view', 'web');
        $user->givePermissionTo('audit.view');

        $expectedContextId = 'user_'.substr(hash_hmac(
            'sha256',
            (string) $user->getAuthIdentifier(),
            (string) config('app.key')
        ), 0, 48);

        $this->actingAs($user)
            ->get('/foundation')
            ->assertOk()
            ->assertDontSee('shared-test-secret-value')
            ->assertDontSee('https://mini-erp-ai.example.test')
            ->assertInertia(fn (Assert $page) => $page
                ->where('csrfToken', fn (mixed $token): bool => is_string($token) && $token !== '')
                ->where('aiAssistant.enabled', true)
                ->where('aiAssistant.scriptUrl', '/vendor/mini-erp-ai/widget.js')
                ->where('aiAssistant.apiUrl', '/api/mini-erp-ai')
                ->where('aiAssistant.contextId', $expectedContextId)
                ->where('aiAssistant.voiceEnabled', false)
                ->where('aiAssistant.visionEnabled', true)
                ->missing('aiAssistant.secret')
                ->missing('aiAssistant.serviceUrl')
                ->etc());
    }
}
