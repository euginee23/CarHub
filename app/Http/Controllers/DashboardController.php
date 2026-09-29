<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send the user to the dashboard for their main role. `/dashboard` is where
     * Fortify lands people after login, registration, and email verification.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $route = match (true) {
            $user->is_admin => 'admin.dashboard',
            $user->isVerifiedOwner() => 'owner.dashboard',
            default => 'renter.dashboard',
        };

        return redirect()->route($route, $request->only('verified'));
    }
}
