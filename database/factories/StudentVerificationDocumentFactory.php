<?php

namespace Database\Factories;

use App\Models\StudentVerificationDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentVerificationDocument>
 */
class StudentVerificationDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'file_path' => StudentVerificationDocument::PATH_PREFIX.'/'.fake()->uuid().'.jpg',
            'original_filename' => 'student-id.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'status' => StudentVerificationDocument::STATUS_PENDING,
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => StudentVerificationDocument::STATUS_APPROVED, 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => StudentVerificationDocument::STATUS_REJECTED, 'reviewed_at' => now()]);
    }
}
