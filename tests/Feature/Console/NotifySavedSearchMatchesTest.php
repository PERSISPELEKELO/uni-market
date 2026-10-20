<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\SavedSearchMatchFound;
use Illuminate\Support\Facades\Notification;

it('notifies a student when a new listing matches their saved search term', function () {
    Notification::fake();
    $student = User::factory()->create();
    $savedSearch = SavedSearch::create(['user_id' => $student->id, 'term' => 'calculator', 'created_at' => now()->subDay()]);
    Listing::factory()->create(['title' => 'Scientific calculator', 'created_at' => now()]);

    $this->artisan('saved-searches:notify-matches')->assertSuccessful();

    Notification::assertSentTo($student, SavedSearchMatchFound::class);
    expect($savedSearch->fresh()->last_notified_at)->not->toBeNull();
});

it('does not notify when nothing matches', function () {
    Notification::fake();
    $student = User::factory()->create();
    SavedSearch::create(['user_id' => $student->id, 'term' => 'calculator', 'created_at' => now()->subDay()]);
    Listing::factory()->create(['title' => 'Wooden desk', 'created_at' => now()]);

    $this->artisan('saved-searches:notify-matches');

    Notification::assertNothingSent();
});

it('only reports listings created since the search was last checked, never the same match twice', function () {
    Notification::fake();
    $student = User::factory()->create();
    $this->travelTo(now()->subDays(2));
    SavedSearch::create(['user_id' => $student->id, 'term' => 'calculator', 'created_at' => now()]);

    $this->travelTo(now()->addDay());
    Listing::factory()->create(['title' => 'Scientific calculator', 'created_at' => now()]);

    $this->travelTo(now()->addHour());
    $this->artisan('saved-searches:notify-matches');
    Notification::assertSentToTimes($student, SavedSearchMatchFound::class, 1);

    $this->travelTo(now()->addDay());
    Listing::factory()->create(['title' => 'Another calculator', 'created_at' => now()]);

    $this->travelTo(now()->addHour());
    $this->artisan('saved-searches:notify-matches');

    Notification::assertSentToTimes($student, SavedSearchMatchFound::class, 2);
});

it('also filters by category and condition when the saved search specifies them', function () {
    Notification::fake();
    $student = User::factory()->create();
    $books = Category::factory()->create();
    $furniture = Category::factory()->create();
    SavedSearch::create(['user_id' => $student->id, 'category_id' => $books->id, 'condition' => 'new', 'created_at' => now()->subDay()]);

    Listing::factory()->create(['category_id' => $furniture->id, 'condition' => 'new', 'created_at' => now()]);
    Listing::factory()->create(['category_id' => $books->id, 'condition' => 'good', 'created_at' => now()]);

    $this->artisan('saved-searches:notify-matches');
    Notification::assertNothingSent();

    Listing::factory()->create(['category_id' => $books->id, 'condition' => 'new', 'created_at' => now()]);
    $this->artisan('saved-searches:notify-matches');

    Notification::assertSentTo($student, SavedSearchMatchFound::class);
});
