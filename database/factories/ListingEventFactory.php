<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingEvent>
 */
class ListingEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'user_id' => User::factory(),
            'type' => ListingEvent::TYPE_VIEW,
            'created_at' => now(),
        ];
    }
}
