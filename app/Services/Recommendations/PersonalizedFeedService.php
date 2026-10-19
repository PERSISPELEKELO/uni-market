<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\SearchLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The home page's recommendation carousels. "Recommended for you" and
 * "Because you viewed..." only ever use a student's own activity
 * (listing_events, search_logs) - never another student's individual
 * activity - and are hidden entirely rather than faked when there isn't
 * enough signal yet. Trending/Good deals/Just listed are real, global
 * signals shown to everyone, including guests, as the cold-start fallback.
 *
 * A listing is never recommended to its own seller, and never once it's
 * sold or suspended (both already excluded by Listing::scopeActive()).
 * There is no seller-blocking/reporting concept in the app to exclude
 * reported sellers by, so that part of the brief isn't implemented -
 * nothing to build it from without inventing a new feature.
 */
class PersonalizedFeedService
{
    private const LIMIT = 10;

    private const RECENT_ACTIVITY_DAYS = 14;

    private const EAGER_LOAD = ['seller:id,name,is_verified,avatar_path', 'category:id,name'];

    /**
     * Blends the categories a student has recently viewed, saved or
     * searched for into a feed of other active listings in those
     * categories. Empty when personalisation is off or there's no recent
     * activity to build a feed from - the caller falls back to the
     * cold-start sections in that case, never a fabricated list.
     */
    public function recommendedForYou(?User $user): Collection
    {
        if ($user === null || ! $user->personalize_recommendations) {
            return collect();
        }

        $categoryIds = $this->recentlyEngagedCategoryIds($user);

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return Listing::active()
            ->whereIn('category_id', $categoryIds)
            ->where('user_id', '!=', $user->id)
            ->with(self::EAGER_LOAD)
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * @return array{listing: Listing, items: Collection<int, Listing>}|null
     */
    public function becauseYouViewed(?User $user): ?array
    {
        if ($user === null || ! $user->personalize_recommendations) {
            return null;
        }

        $lastViewedListingId = ListingEvent::where('user_id', $user->id)
            ->where('type', ListingEvent::TYPE_VIEW)
            ->latest('created_at')
            ->value('listing_id');

        if (! $lastViewedListingId) {
            return null;
        }

        $listing = Listing::find($lastViewedListingId);

        if (! $listing || $listing->user_id === $user->id) {
            return null;
        }

        $items = Listing::active()
            ->whereIn('id', $listing->similarities()->pluck('similar_listing_id'))
            ->with(self::EAGER_LOAD)
            ->get()
            ->sortByDesc(fn (Listing $l) => $listing->similarities->firstWhere('similar_listing_id', $l->id)?->score ?? 0)
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return ['listing' => $listing, 'items' => $items];
    }

    /**
     * Real view counts in the last 24 hours versus the 24 hours before -
     * never a fabricated "trending" label with no signal behind it.
     */
    public function trending(): Collection
    {
        $recentCounts = ListingEvent::where('type', ListingEvent::TYPE_VIEW)
            ->where('created_at', '>=', now()->subHours(24))
            ->get()
            ->countBy('listing_id');

        if ($recentCounts->isEmpty()) {
            return collect();
        }

        $topIds = $recentCounts->sortDesc()->take(self::LIMIT)->keys();

        $listings = Listing::active()->whereIn('id', $topIds)->with(self::EAGER_LOAD)->get()->keyBy('id');

        return $topIds->map(fn (int $id) => $listings->get($id))->filter()->values();
    }

    /**
     * Active listings priced genuinely below the median sold price for
     * their own category and condition - the same comparison
     * SellerInsightsService::priceCheck() already uses for a seller's own
     * listings, applied market-wide here instead.
     */
    public function goodDeals(): Collection
    {
        $medians = Transaction::query()
            ->where('transactions.status', 'COMPLETED')
            ->join('listings', 'listings.id', '=', 'transactions.listing_id')
            ->select('listings.category_id', 'listings.condition', 'transactions.amount')
            ->get()
            ->groupBy(fn ($row) => $row->category_id.'|'.$row->condition)
            ->map(fn (Collection $group) => $this->median($group->pluck('amount')->map(fn ($v) => (float) $v)->sort()->values()));

        if ($medians->isEmpty()) {
            return collect();
        }

        return Listing::active()
            ->with(self::EAGER_LOAD)
            ->get()
            ->filter(function (Listing $listing) use ($medians) {
                $median = $medians->get($listing->category_id.'|'.$listing->condition);

                return $median !== null && (float) $listing->price < $median;
            })
            ->sortBy('price')
            ->take(self::LIMIT)
            ->values();
    }

    public function justListed(): Collection
    {
        return Listing::active()
            ->where('created_at', '>=', now()->subHours(24))
            ->with(self::EAGER_LOAD)
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * Categories behind the student's own recent views, saves and
     * searches (matched against category names) - their own activity
     * only, nothing from any other student.
     */
    private function recentlyEngagedCategoryIds(User $user): Collection
    {
        $since = now()->subDays(self::RECENT_ACTIVITY_DAYS);

        $viewedCategoryIds = ListingEvent::where('listing_events.user_id', $user->id)
            ->whereIn('listing_events.type', [ListingEvent::TYPE_VIEW, ListingEvent::TYPE_SAVE])
            ->where('listing_events.created_at', '>=', $since)
            ->join('listings', 'listings.id', '=', 'listing_events.listing_id')
            ->pluck('listings.category_id');

        $searchedCategoryIds = SearchLog::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->get()
            ->flatMap(fn (SearchLog $log) => Category::where('name', 'like', '%'.$log->term.'%')->pluck('id'));

        return $viewedCategoryIds->merge($searchedCategoryIds)->unique()->values();
    }

    private function median(Collection $sorted): ?float
    {
        $count = $sorted->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($sorted->get($middle - 1) + $sorted->get($middle)) / 2;
        }

        return $sorted->get($middle);
    }
}
