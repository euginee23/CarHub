<?php

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('confirmation is recorded in both parties\' notification feeds', function () {
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create();

    app(TransitionBooking::class)->handle($booking, BookingStatus::Confirmed);

    $renterNotice = $booking->renter->notifications()->sole();
    $ownerNotice = $booking->owner->notifications()->sole();

    expect($renterNotice->data['title'])->toBe('Payment received — booking confirmed')
        ->and($renterNotice->data['url'])->toBe(route('trips.show', $booking))
        ->and($ownerNotice->data['title'])->toBe('Booking confirmed and paid')
        ->and($ownerNotice->data['url'])->toBe(route('owner.bookings.show', $booking));
});

test('the bell shows unread notifications and opening one marks it read', function () {
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create();
    app(TransitionBooking::class)->handle($booking, BookingStatus::Confirmed);
    $renter = $booking->renter;

    $this->actingAs($renter)->get(route('renter.dashboard'))->assertSee('notifications-button', escape: false);

    $component = Livewire::actingAs($renter)
        ->test('notifications.bell')
        ->assertSee('Payment received — booking confirmed')
        ->assertSee('1 unread notification');

    $component->call('open', $renter->notifications()->sole()->id)->assertRedirect(route('trips.show', $booking));

    expect($renter->unreadNotifications()->count())->toBe(0);
});

test('all notifications can be marked as read at once', function () {
    $renter = User::factory()->create();
    foreach (Booking::factory()->count(2)->status(BookingStatus::AwaitingPayment)->for($renter, 'renter')->create() as $booking) {
        app(TransitionBooking::class)->handle($booking, BookingStatus::Confirmed);
    }

    Livewire::actingAs($renter)->test('notifications.bell')->call('markAllAsRead')->assertDontSee('unread notification');

    expect($renter->unreadNotifications()->count())->toBe(0);
});

test('a user cannot open someone else\'s notification', function () {
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create();
    app(TransitionBooking::class)->handle($booking, BookingStatus::Confirmed);

    $component = Livewire::actingAs(User::factory()->create())->test('notifications.bell');

    expect(fn () => $component->call('open', $booking->renter->notifications()->sole()->id))
        ->toThrow(ModelNotFoundException::class);
});

test('a confirmed trip shows the exact pickup point and the payment', function () {
    $booking = Booking::factory()->confirmed()->create();
    $payment = Payment::factory()->paid()->for($booking)->create();

    $this->actingAs($booking->renter)
        ->get(route('trips.show', $booking))
        ->assertOk()
        ->assertSee('Your booking is confirmed')
        ->assertSee('Pickup point')
        ->assertSeeHtml('exact: true')
        ->assertSee($payment->reference)
        ->assertSee($booking->owner->phone);
});

test('the exact pickup point stays hidden until the booking is paid', function () {
    $booking = Booking::factory()->status(BookingStatus::AwaitingPayment)->create(['payment_due_at' => now()->addDay()]);

    $this->actingAs($booking->renter)
        ->get(route('trips.show', $booking))
        ->assertOk()
        ->assertDontSee('Pickup point')
        ->assertDontSeeHtml('exact: true');
});

test('the owner sees the payment on the booking', function () {
    $booking = Booking::factory()->confirmed()->create();
    $payment = Payment::factory()->paid()->for($booking)->create();

    $this->actingAs($booking->owner)
        ->get(route('owner.bookings.show', $booking))
        ->assertOk()
        ->assertSee($payment->reference)
        ->assertSee('Paid');
});
