<?php

use App\Models\User;
use Database\Seeders\InsightsDemoSeeder;

beforeEach(fn () => $this->withoutVite());

it('refuses to run outside the local environment', function () {
    expect(app()->environment())->not->toBe('local');

    (new InsightsDemoSeeder)->run();

    expect(User::count())->toBe(0);
});

it('generates students with a real gender distribution and a computed year of study', function () {
    app()['env'] = 'local';

    (new InsightsDemoSeeder)->run();

    $students = User::where('role', 'student')->get();
    $genders = $students->pluck('gender')->unique()->sort()->values()->all();
    $yearsOfStudy = $students->whereNotNull('intake_year')->pluck('year_of_study');

    expect($students->count())->toBeGreaterThanOrEqual(60)
        ->and($genders)->toBe(['female', 'male', 'undisclosed'])
        ->and($yearsOfStudy->min())->toBeGreaterThanOrEqual(1)
        ->and($yearsOfStudy->max())->toBeLessThanOrEqual(4);
});
