<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send the user to the dashboard for their kind of account. Rental companies
     * that are not verified yet land on their verification application, and
     * renters on their ID verification until two IDs are approved. `/dashboard` is where
     * Fortify lands people after login, registration, and email verification.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $route = match ($user->role) {
            UserRole::Admin => 'admin.dashboard',
            UserRole::Company => $user->isVerifiedCompany() ? 'company.dashboard' : 'company.apply',
            UserRole::Renter => $user->hasVerifiedIdentity() ? 'renter.dashboard' : 'identity.edit',
        };

        return redirect()->route($route, $request->only('verified'));
    }
}
