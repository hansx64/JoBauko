<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsureOrganizationMember;
use App\Http\Middleware\EnsureOrganizationPermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InvalidateMatches;
use App\Http\Middleware\ProtectPrivatePages;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            'throttle:web',
            SecurityHeaders::class,
            ProtectPrivatePages::class,
            EnsureAccountIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'account' => EnsureAccountType::class,
            'org.member' => EnsureOrganizationMember::class,
            'org.can' => EnsureOrganizationPermission::class,
            'matches.invalidate' => InvalidateMatches::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // 419: the session ended (sign-out elsewhere, idle timeout) or the
            // form's security token is stale. Explain instead of "Page Expired".
            if ($response->getStatusCode() === 419) {
                return $request->user()
                    ? back()->with('error', 'This page had expired, so nothing was saved. Please try again.')
                    : to_route('login')->with('status', 'Your session has ended. Please sign in again.');
            }

            // 429 during in-app navigation: stay on the page with a message.
            if ($response->getStatusCode() === 429 && $request->header('X-Inertia')) {
                return back()->with('error', 'Too many requests. Please wait a minute, then try again.');
            }

            return $response;
        });
    })->create();
