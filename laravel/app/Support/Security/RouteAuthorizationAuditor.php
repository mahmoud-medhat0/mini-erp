<?php

namespace App\Support\Security;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class RouteAuthorizationAuditor
{
    /**
     * Central service-authorized allowlist with non-empty reason strings.
     *
     * @var array<string, string>
     */
    public const SERVICE_AUTHORIZED_ALLOWLIST = [
        'foundation' => 'Redirects authenticated user to dashboard without tenant/company context',
        'logout' => 'Standard authenticated session termination handler',
        'notifications' => 'User-scoped notification feed authorized by authenticated session user',
        'notifications.read_all' => 'User-scoped notification state update authorized by authenticated session user',
        'notifications.read' => 'User-scoped notification item update authorized by authenticated session user',
        'attachments.index' => 'Entity attachment access authorized internally by AttachmentService/model policy',
        'attachments.store' => 'Entity attachment creation authorized internally by AttachmentService/model policy',
        'attachments.show' => 'Entity attachment download authorized internally by AttachmentService/model policy',
        'attachments.destroy' => 'Entity attachment deletion authorized internally by AttachmentService/model policy',
    ];

    /**
     * Explicit unauthenticated route allowlist.
     *
     * Keys may be route names (`name:health`) or route URIs (`uri:up`).
     *
     * @var array<string, string>
     */
    public const PUBLIC_ALLOWLIST = [
        'name:health' => 'Application health probe with no sensitive payload',
        'name:locale.update' => 'Locale preference update stores only a language choice in session',
        'name:locale.prefixed_redirect' => 'Legacy locale-prefixed URL normalizer that redirects to the active authenticated route without exposing business data',
        'uri:up' => 'Laravel framework uptime probe with no sensitive payload',
        'uri:_inertia/devtools/entries' => 'Inertia development tooling route with no ERP business payload',
        'uri:_inertia/devtools/entries/{id}' => 'Inertia development tooling route with no ERP business payload',
    ];

    /**
     * Get the service-authorized allowlist map.
     *
     * @return array<string, string>
     */
    public static function allowlist(): array
    {
        return self::SERVICE_AUTHORIZED_ALLOWLIST;
    }

    /**
     * Get the explicit public route allowlist map.
     *
     * @return array<string, string>
     */
    public static function publicAllowlist(): array
    {
        return self::PUBLIC_ALLOWLIST;
    }

    /**
     * Audit routes and return full classification details and summary.
     *
     * @return array{
     *     total: int,
     *     counts: array<string, int>,
     *     failures: list<array{name: ?string, uri: string, methods: list<string>, middleware: list<string>}>,
     *     allowlisted: list<array{name: string, uri: string, methods: list<string>, reason: string}>,
     *     public_allowlisted: list<array{name: ?string, uri: string, methods: list<string>, reason: string}>,
     *     routes: list<array{category: string, name: ?string, uri: string, methods: list<string>, middleware: list<string>, reason: ?string}>
     * }
     */
    public function audit(?RouteCollectionInterface $routes = null): array
    {
        $routes = $routes ?? RouteFacade::getRoutes();

        $counts = [
            'public' => 0,
            'guest' => 0,
            'explicitly_authorized' => 0,
            'service_authorized_allowlist' => 0,
            'failing' => 0,
        ];

        $failures = [];
        $allowlisted = [];
        $publicAllowlisted = [];
        $classifiedRoutes = [];

        foreach ($routes as $route) {
            $classification = $this->classify($route);
            $category = $classification['category'];

            $counts[$category]++;
            $classifiedRoutes[] = $classification;

            if ($category === 'failing') {
                $failures[] = [
                    'name' => $classification['name'],
                    'uri' => $classification['uri'],
                    'methods' => $classification['methods'],
                    'middleware' => $classification['middleware'],
                ];
            } elseif ($category === 'service_authorized_allowlist') {
                $allowlisted[] = [
                    'name' => (string) $classification['name'],
                    'uri' => $classification['uri'],
                    'methods' => $classification['methods'],
                    'reason' => (string) $classification['reason'],
                ];
            } elseif ($category === 'public') {
                $publicAllowlisted[] = [
                    'name' => $classification['name'],
                    'uri' => $classification['uri'],
                    'methods' => $classification['methods'],
                    'reason' => (string) $classification['reason'],
                ];
            }
        }

        return [
            'total' => count($classifiedRoutes),
            'counts' => $counts,
            'failures' => $failures,
            'allowlisted' => $allowlisted,
            'public_allowlisted' => $publicAllowlisted,
            'routes' => $classifiedRoutes,
        ];
    }

    /**
     * Classify a single route into exactly one category.
     *
     * @return array{
     *     category: 'public'|'guest'|'explicitly_authorized'|'service_authorized_allowlist'|'failing',
     *     name: ?string,
     *     uri: string,
     *     methods: list<string>,
     *     middleware: list<string>,
     *     reason: ?string
     * }
     */
    public function classify(Route $route): array
    {
        /** @var list<string> $middleware */
        $middleware = $route->gatherMiddleware();
        $name = $route->getName();
        $uri = $route->uri();
        /** @var list<string> $methods */
        $methods = $route->methods();

        $isAuth = in_array('auth', $middleware, true);
        $isGuest = in_array('guest', $middleware, true);

        $hasExplicitAuth = collect($middleware)->contains(
            static fn (string $entry): bool => Str::startsWith($entry, ['can:', 'permission.any:', 'permission.all:'])
        );

        if ($isGuest) {
            $category = 'guest';
            $reason = null;
        } elseif (! $isAuth && $this->isPublicAllowlisted($route)) {
            $category = 'public';
            $reason = $this->publicAllowlistReason($route);
        } elseif (! $isAuth) {
            $category = 'failing';
            $reason = 'Unauthenticated route is not documented in the public allowlist';
        } elseif ($hasExplicitAuth) {
            $category = 'explicitly_authorized';
            $reason = null;
        } elseif ($name !== null && array_key_exists($name, self::SERVICE_AUTHORIZED_ALLOWLIST)) {
            $category = 'service_authorized_allowlist';
            $reason = self::SERVICE_AUTHORIZED_ALLOWLIST[$name];
        } else {
            $category = 'failing';
            $reason = null;
        }

        return [
            'category' => $category,
            'name' => $name,
            'uri' => $uri,
            'methods' => $methods,
            'middleware' => $middleware,
            'reason' => $reason,
        ];
    }

    private function isPublicAllowlisted(Route $route): bool
    {
        return $this->publicAllowlistReason($route) !== null;
    }

    private function publicAllowlistReason(Route $route): ?string
    {
        $name = $route->getName();

        if ($name !== null) {
            $nameKey = 'name:'.$name;

            if (array_key_exists($nameKey, self::PUBLIC_ALLOWLIST)) {
                return self::PUBLIC_ALLOWLIST[$nameKey];
            }
        }

        $uriKey = 'uri:'.$route->uri();

        if (array_key_exists($uriKey, self::PUBLIC_ALLOWLIST)) {
            return self::PUBLIC_ALLOWLIST[$uriKey];
        }

        return null;
    }
}
