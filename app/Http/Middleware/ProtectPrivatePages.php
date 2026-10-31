<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps pages seen while signed in from being shown again after signing out,
 * for example with the browser's Back button on a shared computer:
 *
 * - the browser is told not to store them (no-store also keeps them out of
 *   the back/forward cache);
 * - Inertia encrypts the page data it keeps in browser history, and the key
 *   is discarded on sign-out (Inertia::clearHistory()), so old entries can no
 *   longer be read. This needs HTTPS or localhost.
 */
class ProtectPrivatePages
{
    public function handle(Request $request, Closure $next): Response
    {
        $signedIn = $request->user() !== null;

        if ($signedIn) {
            Inertia::encryptHistory();
        }

        $response = $next($request);

        // Also covers the sign-out request itself, which starts signed in.
        if ($signedIn || $request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
