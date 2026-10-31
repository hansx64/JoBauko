<?php

namespace App\Http\Middleware;

use App\Services\Recommendation\MatchRefresher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After any change a seeker makes to their profile, mark their
 * recommendations as needing recomputation.
 */
class InvalidateMatches
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe() && $request->user()) {
            MatchRefresher::invalidateUser($request->user()->id);
        }

        return $response;
    }
}
