<?php

namespace Database\Factories;

use App\Models\Appeal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appeal>
 */
class AppealFactory extends Factory
{
    protected $model = Appeal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'target_type' => 'Listing',
            'target_id' => (string) fake()->numberBetween(1, 500),
            'reason' => fake()->sentence(12),
            'evidence_urls' => ['https://evidence.example.com/' . fake()->uuid() . '.jpg'],
            'status' => Appeal::STATUS_PENDING,
            'governance_notes' => null,
            'resolved_at' => null,
        ];
    }
}
