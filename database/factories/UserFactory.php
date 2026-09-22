<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'student_id' => (string) fake()->unique()->numerify('20##########'),
            'role' => 'student',
            'is_verified' => true,
            'student_verification_status' => User::STUDENT_VERIFICATION_VERIFIED,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A brand new member: email confirmed but no student document submitted yet.
     */
    public function pendingStudentVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'student_verification_status' => User::STUDENT_VERIFICATION_NOT_SUBMITTED,
        ]);
    }

    /**
     * A member whose student document is waiting for an administrator.
     */
    public function studentVerificationSubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'student_verification_status' => User::STUDENT_VERIFICATION_PENDING,
            'student_verification_submitted_at' => now(),
        ]);
    }
}
