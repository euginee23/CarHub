<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRenterIsVerified
{
    /**
     * Keep renters on their ID verification until an administrator has approved
     * two government IDs; only then can they use the renting area.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isRenter() && ! $user->hasVerifiedIdentity()) {
            return redirect()->route('identity.edit');
        }

        return $next($request);
    }
}
