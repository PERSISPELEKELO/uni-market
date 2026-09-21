<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory()->pending(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'status' => 'PENDING_MEETING',
            'handover_attempts' => 0,
        ];
    }

    public function inInspection(): static
    {
        return $this->state(fn () => [
            'status' => 'ITEM_INSPECTION',
            'handed_over_at' => now(),
            'inspection_ends_at' => now()->addHours(48),
            'inspection_expires_at' => now()->addHours(48),
        ]);
    }
}
