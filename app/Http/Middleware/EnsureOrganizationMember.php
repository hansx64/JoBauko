<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an active membership in an existing organization.
 */
class EnsureOrganizationMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()?->organizationMembership;

        abort_unless($membership?->is_active && $membership->organization !== null, 403, 'You are not an active member of an organization.');

        return $next($request);
    }
}
