<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Keep each kind of account in its own area. Renters rent, owners list, and
     * administrators run the platform; anyone in the wrong area is sent back to
     * their own dashboard.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        if (! $role instanceof UserRole || ! in_array($role->value, $roles, true)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
