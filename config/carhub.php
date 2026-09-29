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

];
