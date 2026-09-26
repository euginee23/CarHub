<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleSeeder extends Seeder
{
    /**
     * Seed the marketplace with a set of listed vehicles around Metro Cebu,
     * each owned by a verified owner account.
     */
    public function run(): void
    {
        foreach ($this->vehicles() as $attributes) {
            $ownerName = $attributes['owner'];

            $email = Str::slug($ownerName, '.').'@carhub.test';

            $owner = User::firstWhere('email', $email)
                ?? User::factory()->verifiedOwner()->create(['name' => $ownerName, 'email' => $email]);

            $vehicle = new Vehicle(collect($attributes)->except(['owner', 'slug', 'featured', 'rating', 'trips_count'])->all());
            $vehicle->forceFill([
                'owner_id' => $owner->id,
                'slug' => $attributes['slug'],
                'status' => 'listed',
                'featured' => $attributes['featured'],
                'rating' => $attributes['rating'],
                'trips_count' => $attributes['trips_count'],
            ])->save();
        }
    }

    /**
     * The seeded listings.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function vehicles(): array
    {
        return [
            ['slug' => 'toyota-vios-2022', 'brand' => 'Toyota', 'model' => 'Vios', 'year' => 2022, 'type' => 'Sedan', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'seats' => 5, 'price_per_day' => 1800, 'rating' => 4.8, 'trips_count' => 124, 'location' => 'Cebu City', 'latitude' => 10.3077, 'longitude' => 123.8774, 'featured' => true, 'instant_book' => true, 'description' => 'A dependable, fuel-sipping sedan that handles city traffic and long provincial drives equally well. Recently serviced, spotless interior, and easy to park.', 'features' => ['Air conditioning', 'Bluetooth audio', 'Dashcam', 'GPS tracker', 'USB charging', 'Reverse camera'], 'owner' => 'Marco Villanueva'],
            ['slug' => 'toyota-fortuner-2023', 'brand' => 'Toyota', 'model' => 'Fortuner', 'year' => 2023, 'type' => 'SUV', 'transmission' => 'Automatic', 'fuel' => 'Diesel', 'seats' => 7, 'price_per_day' => 4200, 'rating' => 4.9, 'trips_count' => 87, 'location' => 'Cebu City', 'latitude' => 10.3117, 'longitude' => 123.8894, 'featured' => true, 'instant_book' => true, 'description' => 'Seven-seat SUV with the ground clearance for mountain roads and the cabin space for a full family trip. A favourite for out-of-town weekends.', 'features' => ['Air conditioning', '7 seats', 'Apple CarPlay', 'GPS tracker', 'Roof rack', 'Cruise control'], 'owner' => 'Rhea Salazar'],
            ['slug' => 'honda-civic-2021', 'brand' => 'Honda', 'model' => 'Civic', 'year' => 2021, 'type' => 'Sedan', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'seats' => 5, 'price_per_day' => 2500, 'rating' => 4.7, 'trips_count' => 96, 'location' => 'Mandaue City', 'latitude' => 10.3236, 'longitude' => 123.9183, 'featured' => true, 'instant_book' => false, 'description' => 'Sharp handling and a quiet cabin make this a comfortable pick for business trips and airport runs. Turbocharged and surprisingly economical.', 'features' => ['Air conditioning', 'Bluetooth audio', 'Push start', 'GPS tracker', 'Lane assist', 'Leather seats'], 'owner' => 'Jomar Ancheta'],
            ['slug' => 'mitsubishi-montero-2022', 'brand' => 'Mitsubishi', 'model' => 'Montero Sport', 'year' => 2022, 'type' => 'SUV', 'transmission' => 'Automatic', 'fuel' => 'Diesel', 'seats' => 7, 'price_per_day' => 3900, 'rating' => 4.8, 'trips_count' => 73, 'location' => 'Lapu-Lapu City', 'latitude' => 10.3143, 'longitude' => 123.9574, 'featured' => true, 'instant_book' => true, 'description' => 'Powerful diesel SUV with plenty of luggage room. Ideal for beach trips, group travel, and anything involving rough barangay roads.', 'features' => ['Air conditioning', '7 seats', 'Rear entertainment', 'GPS tracker', 'Tow hitch', '360 camera'], 'owner' => 'Christian Neri'],
            ['slug' => 'toyota-hiace-2021', 'brand' => 'Toyota', 'model' => 'Hiace Commuter', 'year' => 2021, 'type' => 'Van', 'transmission' => 'Manual', 'fuel' => 'Diesel', 'seats' => 15, 'price_per_day' => 6500, 'rating' => 4.6, 'trips_count' => 58, 'location' => 'Cebu City', 'latitude' => 10.3237, 'longitude' => 123.8854, 'featured' => true, 'instant_book' => false, 'description' => 'Fifteen-seater van built for group tours, company outings, and airport transfers. Driver service available on request.', 'features' => ['Air conditioning', '15 seats', 'Large luggage bay', 'GPS tracker', 'PA system', 'Curtains'], 'owner' => 'Hannah Claire Pontillas'],
            ['slug' => 'ford-ranger-2023', 'brand' => 'Ford', 'model' => 'Ranger', 'year' => 2023, 'type' => 'Pickup', 'transmission' => 'Automatic', 'fuel' => 'Diesel', 'seats' => 5, 'price_per_day' => 4500, 'rating' => 4.9, 'trips_count' => 41, 'location' => 'Talisay City', 'latitude' => 10.2367, 'longitude' => 123.8414, 'featured' => true, 'instant_book' => true, 'description' => 'Modern pickup with a comfortable cabin and a bed that swallows anything you throw at it. Equally at home hauling cargo or heading up to the highlands.', 'features' => ['Air conditioning', '4x4', 'Bed liner', 'GPS tracker', 'Apple CarPlay', 'Tow package'], 'owner' => 'Dennis Abadiano'],
            ['slug' => 'suzuki-swift-2020', 'brand' => 'Suzuki', 'model' => 'Swift', 'year' => 2020, 'type' => 'Hatchback', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'seats' => 5, 'price_per_day' => 1200, 'rating' => 4.5, 'trips_count' => 189, 'location' => 'Mandaue City', 'latitude' => 10.3196, 'longitude' => 123.9263, 'featured' => false, 'instant_book' => true, 'description' => 'The cheapest way to get around the city on four wheels. Tiny turning radius, great mileage, and easy to slot into any parking space.', 'features' => ['Air conditioning', 'Bluetooth audio', 'GPS tracker', 'USB charging'], 'owner' => 'Kaye Lim'],
            ['slug' => 'nissan-navara-2022', 'brand' => 'Nissan', 'model' => 'Navara', 'year' => 2022, 'type' => 'Pickup', 'transmission' => 'Manual', 'fuel' => 'Diesel', 'seats' => 5, 'price_per_day' => 3800, 'rating' => 4.6, 'trips_count' => 52, 'location' => 'Consolacion', 'latitude' => 10.3766, 'longitude' => 123.9533, 'featured' => false, 'instant_book' => false, 'description' => 'Workhorse pickup for hauling and site visits. Manual transmission, tough suspension, and a bed that has seen real use.', 'features' => ['Air conditioning', '4x2', 'Bed liner', 'GPS tracker', 'Tow hitch'], 'owner' => 'Arnel Bacus'],
            ['slug' => 'toyota-innova-2021', 'brand' => 'Toyota', 'model' => 'Innova', 'year' => 2021, 'type' => 'Van', 'transmission' => 'Automatic', 'fuel' => 'Diesel', 'seats' => 8, 'price_per_day' => 3200, 'rating' => 4.7, 'trips_count' => 143, 'location' => 'Cebu City', 'latitude' => 10.3197, 'longitude' => 123.8934, 'featured' => false, 'instant_book' => true, 'description' => 'The family MPV that never quits. Eight seats, generous headroom, and low running costs for longer provincial routes.', 'features' => ['Air conditioning', '8 seats', 'Bluetooth audio', 'GPS tracker', 'Reverse camera'], 'owner' => 'Grace Tabotabo'],
            ['slug' => 'honda-brv-2023', 'brand' => 'Honda', 'model' => 'BR-V', 'year' => 2023, 'type' => 'SUV', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'seats' => 7, 'price_per_day' => 2900, 'rating' => 4.8, 'trips_count' => 61, 'location' => 'Lapu-Lapu City', 'latitude' => 10.3183, 'longitude' => 123.9494, 'featured' => false, 'instant_book' => true, 'description' => 'Compact seven-seater that drives like a small car but carries like an SUV. A smart middle ground for larger groups on a budget.', 'features' => ['Air conditioning', '7 seats', 'Apple CarPlay', 'GPS tracker', 'Push start', 'Cruise control'], 'owner' => 'Rhea Salazar'],
            ['slug' => 'mazda-3-2022', 'brand' => 'Mazda', 'model' => '3', 'year' => 2022, 'type' => 'Hatchback', 'transmission' => 'Automatic', 'fuel' => 'Gasoline', 'seats' => 5, 'price_per_day' => 2700, 'rating' => 4.9, 'trips_count' => 47, 'location' => 'Cebu City', 'latitude' => 10.3077, 'longitude' => 123.8774, 'featured' => false, 'instant_book' => false, 'description' => 'Easily the nicest interior in this price range. A genuinely enjoyable drive for anyone who cares how a car feels on a winding road.', 'features' => ['Air conditioning', 'Leather seats', 'Bose audio', 'GPS tracker', 'Head-up display', 'Lane assist'], 'owner' => 'Jomar Ancheta'],
            ['slug' => 'toyota-wigo-2023', 'brand' => 'Toyota', 'model' => 'Wigo', 'year' => 2023, 'type' => 'Hatchback', 'transmission' => 'Manual', 'fuel' => 'Gasoline', 'seats' => 5, 'price_per_day' => 1400, 'rating' => 4.4, 'trips_count' => 112, 'location' => 'Talisay City', 'latitude' => 10.2407, 'longitude' => 123.8534, 'featured' => false, 'instant_book' => true, 'description' => 'Small, cheap, and honest. Perfect for solo renters and short city errands where fuel economy matters more than anything else.', 'features' => ['Air conditioning', 'Bluetooth audio', 'GPS tracker'], 'owner' => 'Kaye Lim'],
        ];
    }
}
