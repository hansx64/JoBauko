<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the signed-in member's role to grant a permission, e.g.
 * "org.can:manage_personnel". Use after "org.member".
 */
class EnsureOrganizationPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $membership = $request->user()?->organizationMembership;

        abort_unless(
            $membership?->hasPermission(OrganizationPermission::from($permission)),
            403,
            'Your role in this organization does not allow this.',
        );

        return $next($request);
    }
}
