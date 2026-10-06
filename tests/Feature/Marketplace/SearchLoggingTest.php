<?php

use App\Livewire\Marketplace\ListingIndex;
use App\Models\Listing;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('logs a search term of 3 or more characters, lower-cased and trimmed', function () {
    Livewire::test(ListingIndex::class)->set('search', '  CALculator  ');

    expect(SearchLog::where('term', 'calculator')->exists())->toBeTrue();
});

it('does not log a search term shorter than 3 characters', function () {
    Livewire::test(ListingIndex::class)->set('search', 'ab');

    expect(SearchLog::count())->toBe(0);
});

it('records the real number of matching results at the time of the search', function () {
    Listing::factory()->count(2)->create(['title' => 'Calculus textbook', 'status' => 'active']);
    Listing::factory()->create(['title' => 'Unrelated item', 'status' => 'active']);

    Livewire::test(ListingIndex::class)->set('search', 'calculus');

    expect(SearchLog::where('term', 'calculus')->value('results_count'))->toBe(2);
});

it('records a zero-result search as unmet demand, not an error', function () {
    Livewire::test(ListingIndex::class)->set('search', 'ps5 console');

    expect(SearchLog::where('term', 'ps5 console')->value('results_count'))->toBe(0);
});

it('attributes a search to the signed-in user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(ListingIndex::class)->set('search', 'laptop sale');

    expect(SearchLog::where('term', 'laptop sale')->value('user_id'))->toBe($user->id);
});

it('leaves a guest search anonymous', function () {
    Livewire::test(ListingIndex::class)->set('search', 'bicycle deal');

    expect(SearchLog::where('term', 'bicycle deal')->value('user_id'))->toBeNull();
});

it('rate-limits search logging per session so repeated typing cannot flood the table', function () {
    RateLimiter::clear('search-log|'.session()->getId());

    $component = Livewire::test(ListingIndex::class);

    foreach (range(1, 25) as $i) {
        $component->set('search', "search term number {$i}");
    }

    expect(SearchLog::count())->toBeLessThanOrEqual(20);
});
