<?php

/*
|--------------------------------------------------------------------------
| Demo Content
|--------------------------------------------------------------------------
|
| Static marketing copy (testimonials and FAQs) for the public pages. Vehicle
| listings live in the database; see Database\Seeders\VehicleSeeder for the
| sample catalogue.
|
*/

return [

    'testimonials' => [
        [
            'quote' => 'I booked a Fortuner for a weekend in Moalboal and the whole thing took four minutes. The ID check was done before I even finished packing.',
            'name' => 'Patricia Gonzales',
            'role' => 'Renter, Cebu City',
            'rating' => 5,
        ],
        [
            'quote' => 'My Innova used to sit idle five days a week. CarHub suggested a price I would never have picked myself and it has been booked almost every weekend since.',
            'name' => 'Grace Tabotabo',
            'role' => 'Vehicle owner',
            'rating' => 5,
        ],
        [
            'quote' => 'Being able to see the exact vehicle on a map before booking removed all the guesswork. No more meeting a stranger in a mall parking lot hoping for the best.',
            'name' => 'Dennis Abadiano',
            'role' => 'Renter, Talisay City',
            'rating' => 5,
        ],
    ],

    'faqs' => [
        [
            'category' => 'Booking',
            'items' => [
                [
                    'question' => 'What do I need to book a vehicle?',
                    'answer' => 'You need to be at least 21 years old, hold a valid driver\'s license, and upload two valid government-issued IDs. Verification is usually completed within a few minutes and only has to be done once.',
                ],
                [
                    'question' => 'Why do you require two valid IDs?',
                    'answer' => 'Two independent IDs let us confirm that you are who you say you are before a stranger\'s vehicle is handed over to you. It protects owners from fraud and gives renters confidence that everyone on the platform has been through the same check.',
                ],
                [
                    'question' => 'How far in advance can I book?',
                    'answer' => 'Up to 90 days ahead. Bookings must start at least two hours from now, run for a minimum of one day, and cannot overlap with an existing reservation on the same vehicle — the calendar enforces all of this before you reach payment.',
                ],
                [
                    'question' => 'Can I extend a rental that is already running?',
                    'answer' => 'Yes, as long as the vehicle has no booking immediately after yours. Request the extension from your trip page and the additional days are charged at the same daily rate.',
                ],
            ],
        ],
        [
            'category' => 'Payments',
            'items' => [
                [
                    'question' => 'Which payment methods do you accept?',
                    'answer' => 'GCash, Maya, major credit and debit cards, and direct bank transfer. Every payment is validated and confirmed before the booking is finalised — a reservation is never held on an unverified payment.',
                ],
                [
                    'question' => 'When am I charged?',
                    'answer' => 'The full rental amount is authorised when you confirm the booking and captured once the owner accepts. If the owner declines or does not respond within the response window, the authorisation is released in full.',
                ],
                [
                    'question' => 'Is there a security deposit?',
                    'answer' => 'Most owners require a refundable deposit, shown on the vehicle page before you book. It is released within three business days of the vehicle being returned in its original condition.',
                ],
                [
                    'question' => 'What is your cancellation policy?',
                    'answer' => 'Free cancellation up to 24 hours before pickup. Inside 24 hours, the first day of the rental is non-refundable. Owner cancellations are always refunded in full.',
                ],
            ],
        ],
        [
            'category' => 'Safety & tracking',
            'items' => [
                [
                    'question' => 'Are the vehicles tracked?',
                    'answer' => 'Yes. Every listed vehicle carries a GPS unit. Owners can see the vehicle\'s location for the duration of an active rental only, and tracking is disabled the moment a trip ends. Renters are told about this before booking and again at pickup.',
                ],
                [
                    'question' => 'Are rentals insured?',
                    'answer' => 'Every trip includes third-party liability coverage. Comprehensive coverage is available as an add-on at checkout and is strongly recommended for longer trips.',
                ],
                [
                    'question' => 'What happens if the vehicle breaks down?',
                    'answer' => 'Roadside assistance is included on every booking. Report the issue from your trip page and we coordinate with the owner — you are not charged for days lost to a mechanical fault.',
                ],
            ],
        ],
        [
            'category' => 'For owners',
            'items' => [
                [
                    'question' => 'How much can I earn?',
                    'answer' => 'It depends on the vehicle and how often you make it available. Our pricing model reads local demand, season, and what comparable vehicles nearby are charging, then suggests a daily rate — you are always free to override it.',
                ],
                [
                    'question' => 'How do I get paid?',
                    'answer' => 'Payouts are released to your GCash, Maya, or bank account within 24 hours of a trip completing. CarHub takes a 15% service fee, which covers payment processing, insurance, and support.',
                ],
                [
                    'question' => 'Can I choose who rents my vehicle?',
                    'answer' => 'Yes. You can require manual approval on every request and review the renter\'s verification status, rating, and trip history before accepting. Instant Book is optional and off by default.',
                ],
            ],
        ],
    ],

];
