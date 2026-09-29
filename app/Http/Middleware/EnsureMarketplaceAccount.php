<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketplaceAccount
{
    /**
     * Keep administrators out of the renting and hosting areas. Admin accounts run
     * the platform — they review others' documents but never rent, list, or verify
     * themselves — so they are sent back to the admin overview.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
