<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Vehicle;
use Livewire\Livewire;

beforeEach(function () {
    $this->vehicle = Vehicle::factory()->create(['rating' => 4.0, 'reviews_count' => 0]);
    $this->booking = Booking::factory()->forVehicle($this->vehicle)->status(BookingStatus::Completed)->create();
});

test('the renter rates a completed trip', function () {
    Livewire::actingAs($this->booking->renter)
        ->test('pages::trips.show', ['booking' => $this->booking])
        ->assertSee('Rate your trip')
        ->call('$set', 'rating', 5)
        ->set('comment', 'Clean car, easy pickup.')
        ->call('submitReview')
        ->assertHasNoErrors()
        ->assertDontSee('Rate your trip')
        ->assertSee('Clean car, easy pickup.');

    $review = Review::sole();

    expect($review)
        ->rating->toBe(5)
        ->reviewer_id->toBe($this->booking->renter_id)
        ->owner_id->toBe($this->booking->owner_id)
        ->and($this->vehicle->fresh())
        ->rating->toBe(5.0)
        ->reviews_count->toBe(1);
});

test('the vehicle rating is the average of its reviews', function () {
    Review::factory()->for(Booking::factory()->forVehicle($this->vehicle)->status(BookingStatus::Completed))->create(['rating' => 3, 'vehicle_id' => $this->vehicle->id]);

    Livewire::actingAs($this->booking->renter)
        ->test('pages::trips.show', ['booking' => $this->booking])
        ->set('rating', 4)
        ->call('submitReview');

    expect($this->vehicle->fresh())
        ->rating->toBe(3.5)
        ->reviews_count->toBe(2);
});

test('a rating between one and five stars is required', function (int $rating) {
    Livewire::actingAs($this->booking->renter)
        ->test('pages::trips.show', ['booking' => $this->booking])
        ->set('rating', $rating)
        ->call('submitReview')
        ->assertHasErrors('rating');

    expect(Review::count())->toBe(0);
})->with(['no stars' => 0, 'six stars' => 6]);

test('a trip can only be reviewed once', function () {
    Review::factory()->for($this->booking)->create();

    Livewire::actingAs($this->booking->renter)
        ->test('pages::trips.show', ['booking' => $this->booking])
        ->set('rating', 5)
        ->call('submitReview')
        ->assertForbidden();

    expect(Review::count())->toBe(1);
});

test('trips that are not finished cannot be reviewed', function () {
    $booking = Booking::factory()->confirmed()->create();

    Livewire::actingAs($booking->renter)
        ->test('pages::trips.show', ['booking' => $booking])
        ->assertDontSee('Rate your trip')
        ->set('rating', 5)
        ->call('submitReview')
        ->assertForbidden();
});

test('reviews help future renters on the vehicle page', function () {
    $review = Review::factory()->for($this->booking)->create(['rating' => 5, 'comment' => 'Would rent again.']);
    $this->vehicle->forceFill(['rating' => 5.0, 'reviews_count' => 1])->save();

    $this->get(route('vehicles.show', $this->vehicle))
        ->assertOk()
        ->assertSee('Would rent again.')
        ->assertSee($review->reviewer->name)
        ->assertSee('from 1 review')
        ->assertSee('5.0 average rating');
});

test('the owner sees the renter\'s review on the booking', function () {
    Review::factory()->for($this->booking)->create(['comment' => 'Great host.']);

    $this->actingAs($this->booking->owner)->get(route('owner.bookings.show', $this->booking))->assertOk()->assertSee('Great host.');
});
