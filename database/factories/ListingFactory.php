<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 5000),
            'condition' => fake()->randomElement(array_keys(Listing::CONDITIONS)),
            'status' => Listing::STATUS_ACTIVE,
            'images' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Listing::STATUS_PENDING]);
    }

    public function sold(): static
    {
        return $this->state(fn () => ['status' => Listing::STATUS_SOLD]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Listing::STATUS_SUSPENDED]);
    }
}
