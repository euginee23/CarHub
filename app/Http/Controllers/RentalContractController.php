<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RentalContractController extends Controller
{
    /**
     * Show the printable rental contract for a booking.
     */
    public function show(Booking $booking): View
    {
        Gate::authorize('view', $booking);

        $contract = $booking->contract;

        abort_if($contract === null, 404);

        return view('contracts.show', ['contract' => $contract]);
    }
}
