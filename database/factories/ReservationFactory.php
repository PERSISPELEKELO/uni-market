<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'buyer_id' => User::factory(),
            'status' => Reservation::STATUS_ACTIVE,
        ];
    }
}
