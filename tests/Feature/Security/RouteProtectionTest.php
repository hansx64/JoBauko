<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Guards against a new route being added without the right protection.
 * Every route must either be on the public list or require a signed-in user,
 * and each portal area must also check the account type.
 */
class RouteProtectionTest extends TestCase
{
    /**
     * Routes anyone may open, by name. Guest-only routes (sign in, register,
     * password reset, invitations) are recognized by their guest middleware.
     */
    private const PUBLIC = [
        'home',
        'opportunities.index',
        'opportunities.show',
        'privacy',
    ];

    /** URIs outside the application's own routes (framework and tooling). */
    private const IGNORED_PREFIXES = ['up', '_ignition', 'sanctum', 'storage/'];

    /**
     * @return list<Route>
     */
    private function routes(): array
    {
        return array_values(array_filter(Router::getRoutes()->getRoutes(), function (Route $route) {
            foreach (self::IGNORED_PREFIXES as $prefix) {
                if (str_starts_with($route->uri(), $prefix)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @return list<string>
     */
    private function middleware(Route $route): array
    {
        return Router::gatherRouteMiddleware($route);
    }

    private function requiresSignIn(Route $route): bool
    {
        return collect($this->middleware($route))->contains(fn (string $m) => $m === 'auth' || str_starts_with($m, 'Illuminate\Auth\Middleware\Authenticate'));
    }

    private function guestOnly(Route $route): bool
    {
        return collect($this->middleware($route))->contains(fn (string $m) => $m === 'guest' || str_contains($m, 'RedirectIfAuthenticated'));
    }

    public function test_every_private_route_requires_sign_in()
    {
        // The checks themselves must recognize protection correctly.
        $this->assertTrue($this->requiresSignIn(Router::getRoutes()->getByName('admin.dashboard')));
        $this->assertFalse($this->requiresSignIn(Router::getRoutes()->getByName('home')));
        $this->assertTrue($this->guestOnly(Router::getRoutes()->getByName('login')));

        foreach (self::PUBLIC as $name) {
            $this->assertNotNull(Router::getRoutes()->getByName($name), "Public route {$name} no longer exists; remove it from the list.");
        }

        $unprotected = [];

        foreach ($this->routes() as $route) {
            if (! $this->requiresSignIn($route) && ! $this->guestOnly($route) && ! in_array($route->getName(), self::PUBLIC, true)) {
                $unprotected[] = implode('|', $route->methods()).' '.$route->uri().' ('.($route->getName() ?? 'unnamed').')';
            }
        }

        $this->assertSame([], $unprotected, 'These routes are reachable without signing in. Protect them or add them to the public list.');
    }

    public function test_each_area_checks_the_account_type()
    {
        $areas = [
            'admin.' => 'account:admin',
            'org.' => 'account:organization',
            'seeker.' => 'account:seeker',
        ];
        $missing = [];

        foreach ($this->routes() as $route) {
            foreach ($areas as $prefix => $required) {
                if (str_starts_with((string) $route->getName(), $prefix) && ! in_array($required, $route->gatherMiddleware(), true)) {
                    $missing[] = "{$route->getName()} needs {$required}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_state_changing_routes_are_never_get()
    {
        $unsafe = [];

        foreach ($this->routes() as $route) {
            $name = (string) $route->getName();

            if (in_array('GET', $route->methods(), true) && preg_match('/\.(store|update|destroy|delete|approve|reject|suspend|reinstate|deactivate|reactivate|send|decide|withdraw|read-all)$/', $name)) {
                $unsafe[] = $name;
            }
        }

        $this->assertSame([], $unsafe, 'Actions that change data must use POST, PUT, PATCH or DELETE so CSRF protection applies.');
    }
}
