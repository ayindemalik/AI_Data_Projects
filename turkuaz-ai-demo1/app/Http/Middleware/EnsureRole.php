<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow the request through only if the logged-in user's role matches
     * one of the given role slugs.
     *
     * Usage on a route:  ->middleware('role:administrator,sales')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole($roles)) {
            abort(403, 'You do not have access to this section.');
        }

        return $next($request);
    }
}
