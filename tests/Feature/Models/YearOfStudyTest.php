<?php

use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->withoutVite());

it('computes the current year of study from intake_year using the configured academic year start month', function () {
    config(['zut.academic_year_start_month' => 9]);
    Carbon::setTestNow('2026-10-15');

    $firstYear = User::factory()->create(['intake_year' => 2026, 'year_of_study' => null]);
    $secondYear = User::factory()->create(['intake_year' => 2025, 'year_of_study' => null]);

    expect($firstYear->currentYearOfStudy())->toBe(1)
        ->and($secondYear->currentYearOfStudy())->toBe(2);

    Carbon::setTestNow();
});

it('treats the new academic year as not having started yet before the start month', function () {
    config(['zut.academic_year_start_month' => 9]);
    Carbon::setTestNow('2026-03-01');

    $user = User::factory()->create(['intake_year' => 2025, 'year_of_study' => null]);

    // It's March 2026, but the 2026/27 academic year hasn't started yet (starts September),
    // so someone who started in September 2025 is still in their first year.
    expect($user->currentYearOfStudy())->toBe(1);

    Carbon::setTestNow();
});

it('falls back to the stored year_of_study when there is no intake_year', function () {
    $user = User::factory()->create(['intake_year' => null, 'year_of_study' => 3]);

    expect($user->currentYearOfStudy())->toBe(3);
});

it('keeps the stored year_of_study column in sync with intake_year on save', function () {
    config(['zut.academic_year_start_month' => 9]);
    Carbon::setTestNow('2026-10-15');

    $user = User::factory()->create(['intake_year' => 2024, 'year_of_study' => null]);

    expect($user->fresh()->year_of_study)->toBe(3);

    Carbon::setTestNow();
});

it('never lets intake_year override a manually-set year_of_study for accounts with no intake_year', function () {
    $user = User::factory()->create(['intake_year' => null, 'year_of_study' => 4]);

    expect($user->fresh()->year_of_study)->toBe(4);
});

it('defaults gender to undisclosed and never requires it', function () {
    $user = User::factory()->create();

    expect($user->fresh()->gender)->toBe('undisclosed');
});

describe('users:recompute-year-of-study', function () {
    it('updates every user with an intake_year to their current year of study', function () {
        config(['zut.academic_year_start_month' => 9]);
        Carbon::setTestNow('2026-10-15');

        $user = User::factory()->create(['intake_year' => 2024, 'year_of_study' => null]);
        $user->forceFill(['year_of_study' => 1])->saveQuietly();

        $this->artisan('users:recompute-year-of-study')->assertSuccessful();

        expect($user->fresh()->year_of_study)->toBe(3);

        Carbon::setTestNow();
    });

    it('leaves accounts with no intake_year untouched', function () {
        $user = User::factory()->create(['intake_year' => null, 'year_of_study' => 2]);

        $this->artisan('users:recompute-year-of-study')->assertSuccessful();

        expect($user->fresh()->year_of_study)->toBe(2);
    });
});
