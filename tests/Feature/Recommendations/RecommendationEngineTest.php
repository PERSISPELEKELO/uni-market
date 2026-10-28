<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\ListingSimilarity;
use App\Models\ListingView;
use App\Models\RecommendationEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Recommendations\ContentSimilarityService;
use App\Services\Recommendations\CoOccurrenceService;
use App\Services\Recommendations\PersonalizedFeedService;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

describe('content similarity', function () {
    it('scores two listings with genuinely shared distinctive words higher than an unrelated one', function () {
        $books = Category::factory()->create(['name' => 'Books & Textbooks']);
        $furniture = Category::factory()->create(['name' => 'Furniture']);

        $target = Listing::factory()->create([
            'category_id' => $books->id,
            'title' => 'Calculus Stewart Textbook Ninth Edition',
            'description' => 'Calculus textbook for engineering students, Stewart edition, barely used.',
        ]);
        $related = Listing::factory()->create([
            'category_id' => $books->id,
            'title' => 'Stewart Calculus Textbook Eighth Edition',
            'description' => 'Older Stewart calculus textbook, still great for engineering coursework.',
        ]);
        $unrelated = Listing::factory()->create([
            'category_id' => $furniture->id,
            'title' => 'Wooden Study Desk',
            'description' => 'Sturdy wooden desk, perfect for a dorm room.',
        ]);

        app(ContentSimilarityService::class)->refreshFor($target);

        $topMatch = ListingSimilarity::where('listing_id', $target->id)->orderByDesc('score')->first();

        expect($topMatch)->not->toBeNull()
            ->and($topMatch->similar_listing_id)->toBe($related->id);

        expect(ListingSimilarity::where('listing_id', $target->id)->where('similar_listing_id', $unrelated->id)->exists())->toBeFalse();
    });

    it('never includes a sold or suspended listing in the similarity list', function () {
        $category = Category::factory()->create();
        $target = Listing::factory()->create(['category_id' => $category->id, 'title' => 'Gaming Laptop Dell', 'description' => 'Fast gaming laptop with a dedicated graphics card.']);
        Listing::factory()->sold()->create(['category_id' => $category->id, 'title' => 'Gaming Laptop Dell Sold', 'description' => 'Fast gaming laptop with a dedicated graphics card.']);

        app(ContentSimilarityService::class)->refreshAll();

        expect(ListingSimilarity::where('listing_id', $target->id)->count())->toBe(0);
    });

    it('refreshes a single listing without touching every other listing\'s rows', function () {
        $category = Category::factory()->create();
        $a = Listing::factory()->create(['category_id' => $category->id, 'title' => 'Mountain Bike', 'description' => 'Twenty one speed mountain bike, lightly used.']);
        $b = Listing::factory()->create(['category_id' => $category->id, 'title' => 'Road Bike', 'description' => 'Fast road bike, great for campus commuting.']);

        app(ContentSimilarityService::class)->refreshAll();
        $originalCount = ListingSimilarity::count();

        app(ContentSimilarityService::class)->refreshFor($a);

        expect(ListingSimilarity::count())->toBe($originalCount);
        expect($b)->not->toBeNull();
    });
});

describe('co-occurrence ("students also viewed")', function () {
    it('stays hidden below three distinct students', function () {
        $listing = Listing::factory()->create();
        $other = Listing::factory()->create();

        foreach (range(1, 2) as $_) {
            $viewer = User::factory()->create();
            ListingView::create(['listing_id' => $listing->id, 'viewer_id' => $viewer->id, 'session_hash' => hash('sha256', (string) $viewer->id), 'viewed_at' => now()]);
            ListingView::create(['listing_id' => $other->id, 'viewer_id' => $viewer->id, 'session_hash' => hash('sha256', (string) $viewer->id), 'viewed_at' => now()]);
        }

        expect(app(CoOccurrenceService::class)->alsoViewed($listing))->toBeEmpty();
    });

    it('shows a co-viewed listing once three distinct students connect them', function () {
        $listing = Listing::factory()->create();
        $other = Listing::factory()->create(['title' => 'Co-viewed item']);

        foreach (range(1, 3) as $_) {
            $viewer = User::factory()->create();
            ListingView::create(['listing_id' => $listing->id, 'viewer_id' => $viewer->id, 'session_hash' => hash('sha256', (string) $viewer->id), 'viewed_at' => now()]);
            ListingView::create(['listing_id' => $other->id, 'viewer_id' => $viewer->id, 'session_hash' => hash('sha256', (string) $viewer->id), 'viewed_at' => now()]);
        }

        $alsoViewed = app(CoOccurrenceService::class)->alsoViewed($listing);

        expect($alsoViewed->pluck('id'))->toContain($other->id);
    });
});

describe('personalized feed', function () {
    it('shows nothing for a guest', function () {
        expect(app(PersonalizedFeedService::class)->recommendedForYou(null))->toBeEmpty();
    });

    it('shows nothing when the student has turned personalisation off', function () {
        $student = User::factory()->create(['personalize_recommendations' => false]);
        Listing::factory()->create();

        expect(app(PersonalizedFeedService::class)->recommendedForYou($student))->toBeEmpty();
    });

    it('recommends listings from a category the student recently viewed, excluding their own', function () {
        $student = User::factory()->create(['personalize_recommendations' => true]);
        $category = Category::factory()->create();
        $viewedListing = Listing::factory()->create(['category_id' => $category->id]);
        $recommendation = Listing::factory()->create(['category_id' => $category->id, 'title' => 'Another item in the same category']);
        $ownListing = Listing::factory()->for($student, 'seller')->create(['category_id' => $category->id]);

        ListingEvent::create(['listing_id' => $viewedListing->id, 'user_id' => $student->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()]);

        $items = app(PersonalizedFeedService::class)->recommendedForYou($student);

        expect($items->pluck('id'))->toContain($recommendation->id)
            ->and($items->pluck('id'))->not->toContain($ownListing->id);
    });

    it('builds "because you viewed" from the student\'s most recent view and precomputed similarity', function () {
        $student = User::factory()->create(['personalize_recommendations' => true]);
        $category = Category::factory()->create();
        $viewed = Listing::factory()->create(['category_id' => $category->id, 'title' => 'Calculus Textbook', 'description' => 'Stewart calculus textbook for engineering.']);
        $similar = Listing::factory()->create(['category_id' => $category->id, 'title' => 'Stewart Calculus Book', 'description' => 'Calculus textbook, Stewart edition, engineering course.']);

        app(ContentSimilarityService::class)->refreshAll();
        ListingEvent::create(['listing_id' => $viewed->id, 'user_id' => $student->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()]);

        $result = app(PersonalizedFeedService::class)->becauseYouViewed($student);

        expect($result)->not->toBeNull()
            ->and($result['listing']->id)->toBe($viewed->id)
            ->and($result['items']->pluck('id'))->toContain($similar->id);
    });

    it('trending only ever counts views from the last 24 hours', function () {
        $recent = Listing::factory()->create(['title' => 'Recently viewed']);
        $stale = Listing::factory()->create(['title' => 'Viewed long ago']);
        ListingEvent::create(['listing_id' => $recent->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()->subHours(2)]);
        ListingEvent::create(['listing_id' => $stale->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()->subHours(30)]);

        $trending = app(PersonalizedFeedService::class)->trending();

        expect($trending->pluck('id'))->toContain($recent->id)
            ->and($trending->pluck('id'))->not->toContain($stale->id);
    });

    it('flags a listing as a good deal only once it is genuinely below the category/condition median', function () {
        $category = Category::factory()->create();

        foreach ([100, 100, 100] as $amount) {
            $sold = Listing::factory()->create(['category_id' => $category->id, 'condition' => 'good']);
            Transaction::factory()->create(['listing_id' => $sold->id, 'amount' => $amount, 'status' => 'COMPLETED']);
        }

        $cheap = Listing::factory()->create(['category_id' => $category->id, 'condition' => 'good', 'price' => 50]);
        $expensive = Listing::factory()->create(['category_id' => $category->id, 'condition' => 'good', 'price' => 500]);

        $goodDeals = app(PersonalizedFeedService::class)->goodDeals();

        expect($goodDeals->pluck('id'))->toContain($cheap->id)
            ->and($goodDeals->pluck('id'))->not->toContain($expensive->id);
    });

    it('just listed only ever includes listings created in the last 24 hours', function () {
        $fresh = Listing::factory()->create(['title' => 'Brand new', 'created_at' => now()->subHours(2)]);
        $old = Listing::factory()->create(['title' => 'Old listing', 'created_at' => now()->subDays(5)]);

        $justListed = app(PersonalizedFeedService::class)->justListed();

        expect($justListed->pluck('id'))->toContain($fresh->id)
            ->and($justListed->pluck('id'))->not->toContain($old->id);
    });
});

describe('recommendation click attribution', function () {
    it('logs a click when a listing is opened via a tagged recommendation link, and attributes it to the right section', function () {
        $listing = Listing::factory()->create();

        $this->get(route('listings.show', ['listing' => $listing, 'rec' => RecommendationEvent::SECTION_TRENDING]));

        expect(RecommendationEvent::where('listing_id', $listing->id)->where('type', RecommendationEvent::TYPE_CLICK)->where('section', RecommendationEvent::SECTION_TRENDING)->count())->toBe(1);
    });

    it('does not log a click for an ordinary, untagged listing visit', function () {
        $listing = Listing::factory()->create();

        $this->get(route('listings.show', $listing));

        expect(RecommendationEvent::where('type', RecommendationEvent::TYPE_CLICK)->count())->toBe(0);
    });

    it('ignores an invalid section value rather than logging garbage', function () {
        $listing = Listing::factory()->create();

        $this->get(route('listings.show', ['listing' => $listing, 'rec' => 'not-a-real-section']));

        expect(RecommendationEvent::where('type', RecommendationEvent::TYPE_CLICK)->count())->toBe(0);
    });
});
