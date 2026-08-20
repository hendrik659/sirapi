<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Allow the request only when the authenticated user has one of the given role slugs.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            in_array($request->user()?->role?->slug, $roles, true),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
