<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to one or more account types, e.g. "account:admin".
 */
class EnsureAccountType
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $allowed = array_map(fn (string $type) => AccountType::from($type), $types);

        abort_unless(in_array($request->user()?->account_type, $allowed, true), 403);

        return $next($request);
    }
}
