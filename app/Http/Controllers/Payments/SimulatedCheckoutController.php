<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The hosted checkout of the simulated gateway, for local development only.
 */
class SimulatedCheckoutController extends Controller
{
    /**
     * Show the test checkout page.
     */
    public function show(Payment $payment): View
    {
        $this->guard($payment);

        return view('payments.simulated', ['payment' => $payment->load('booking.vehicle')]);
    }

    /**
     * Record the outcome the tester chose, then return to CarHub as a gateway would.
     */
    public function complete(Request $request, Payment $payment): RedirectResponse
    {
        $this->guard($payment);

        $outcome = $request->validate(['outcome' => ['required', 'in:paid,failed']])['outcome'];

        $payment->forceFill(['payload' => [...(array) $payment->payload, 'simulated_outcome' => $outcome]])->save();

        return redirect()->route('payments.return', $payment);
    }

    /**
     * Only simulated payments, outside production, for the booking's renter.
     */
    protected function guard(Payment $payment): void
    {
        abort_if(app()->isProduction() || $payment->provider !== 'simulated' || ! $payment->isPending(), 404);

        Gate::authorize('checkout', $payment->booking);
    }
}
