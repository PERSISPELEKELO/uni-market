<?php

use App\Models\Listing;
use App\Models\ListingView;
use App\Models\SearchLog;

it('deletes listing views and search logs older than 12 months, keeping recent ones', function () {
    $listing = Listing::factory()->create();

    $old = ListingView::create([
        'listing_id' => $listing->id,
        'viewer_id' => null,
        'session_hash' => 'old',
        'viewed_at' => now()->subMonths(13),
    ]);

    $recent = ListingView::create([
        'listing_id' => $listing->id,
        'viewer_id' => null,
        'session_hash' => 'recent',
        'viewed_at' => now()->subMonths(2),
    ]);

    SearchLog::insert([
        ['term' => 'old search', 'results_count' => 1, 'user_id' => null, 'created_at' => now()->subMonths(13)],
        ['term' => 'recent search', 'results_count' => 1, 'user_id' => null, 'created_at' => now()->subMonths(2)],
    ]);

    $this->artisan('insights:prune')->assertExitCode(0);

    expect(ListingView::find($old->id))->toBeNull()
        ->and(ListingView::find($recent->id))->not->toBeNull()
        ->and(SearchLog::where('term', 'old search')->exists())->toBeFalse()
        ->and(SearchLog::where('term', 'recent search')->exists())->toBeTrue();
});
