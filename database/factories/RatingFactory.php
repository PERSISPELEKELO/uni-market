<?php

namespace Database\Factories;

use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rating>
 */
class RatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'rater_id' => User::factory(),
            'rated_id' => User::factory(),
            'stars' => fake()->numberBetween(Rating::MIN_STARS, Rating::MAX_STARS),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
