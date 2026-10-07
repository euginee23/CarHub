<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | The platform service fee charged on top of the owner's rental subtotal,
    | expressed as a fraction (0.15 = 15%).
    |
    */

    'service_fee_rate' => 0.15,

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    |
    | How far ahead renters can book, and the shortest and longest rental.
    | Rentals are charged per started 24-hour period.
    |
    */

    'booking' => [
        'min_lead_hours' => 2,
        'max_advance_days' => 90,
        'min_rental_hours' => 24,
        'max_rental_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    |
    | Distance options for GPS search, in kilometres, and how many vehicles a
    | renter can line up side by side on the comparison page.
    |
    */

    'search_radii' => [5, 10, 25, 50],

    'compare_limit' => 3,

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | `driver` picks the payment gateway: "paymongo" charges through PayMongo
    | (keys live in config/services.php), while "simulated" shows a local test
    | checkout so the flow can be demoed without keys. Simulated payments are
    | refused in production. Renters must pay within `window_hours` of signing
    | the contract, or the booking expires and the vehicle is released.
    |
    */

    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'simulated'),
        'methods' => ['gcash', 'maya', 'card', 'grab_pay'],
        'window_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Handover
    |--------------------------------------------------------------------------
    |
    | How early before pickup time the owner may release the vehicle, and how
    | late a return can be before it counts as late, both in minutes.
    |
    */

    'handover' => [
        'early_release_minutes' => 120,
        'late_grace_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    |
    | `test_page` turns on the admin GPS test page at /test-track-gps-map, for
    | pairing an ESP or phone with a vehicle and watching it on the map. It is
    | on outside production unless TRACKING_TEST_PAGE says otherwise.
    |
    */

    'tracking' => [
        'test_page' => (bool) env('TRACKING_TEST_PAGE', env('APP_ENV') !== 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demand forecasting
    |--------------------------------------------------------------------------
    |
    | `php artisan demand:forecast` (weekly) trains an LSTM per body type in
    | ml/forecast.py on `history_days` of booking requests and predicts the next
    | `horizon` days. Body types with less than `min_history_days` of history,
    | or any run where Python is unavailable, use the seasonal fallback.
    |
    */

    'forecasting' => [
        'python' => env('FORECAST_PYTHON', base_path('ml/.venv/bin/python')),
        'script' => base_path('ml/forecast.py'),
        'history_days' => 365,
        'min_history_days' => 90,
        'lookback' => 28,
        'horizon' => 30,
        'timeout' => 600,
    ],

];
