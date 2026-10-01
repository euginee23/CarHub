<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send the user to the dashboard for their kind of account. Owners who are not
     * verified yet land on their verification application. `/dashboard` is where
     * Fortify lands people after login, registration, and email verification.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $route = match ($user->role) {
            UserRole::Admin => 'admin.dashboard',
            UserRole::Owner => $user->isVerifiedOwner() ? 'owner.dashboard' : 'owner.apply',
            UserRole::Renter => 'renter.dashboard',
        };

        return redirect()->route($route, $request->only('verified'));
    }
}
