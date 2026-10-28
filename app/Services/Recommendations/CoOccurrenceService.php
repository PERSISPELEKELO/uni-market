<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\Listing;
use App\Models\ListingView;
use Illuminate\Support\Collection;

/**
 * "Students who viewed this also viewed" - item-to-item co-occurrence from
 * real listing_views rows. Only ever shown once at least `MIN_DISTINCT_
 * STUDENTS` different signed-in students connect the two listings, per the
 * brief - a pair seen together by one or two students isn't a pattern,
 * it's coincidence, and showing it would risk implying something about
 * those specific students' activity.
 */
class CoOccurrenceService
{
    private const MIN_DISTINCT_STUDENTS = 3;

    private const LIMIT = 8;

    /**
     * @return Collection<int, Listing>
     */
    public function alsoViewed(Listing $listing): Collection
    {
        $viewerIds = ListingView::where('listing_id', $listing->id)
            ->whereNotNull('viewer_id')
            ->distinct()
            ->pluck('viewer_id');

        if ($viewerIds->count() < self::MIN_DISTINCT_STUDENTS) {
            return collect();
        }

        $coViewedCounts = ListingView::whereIn('viewer_id', $viewerIds)
            ->where('listing_id', '!=', $listing->id)
            ->get(['listing_id', 'viewer_id'])
            ->groupBy('listing_id')
            ->map(fn (Collection $rows) => $rows->pluck('viewer_id')->unique()->count())
            ->filter(fn (int $distinctViewers) => $distinctViewers >= self::MIN_DISTINCT_STUDENTS)
            ->sortDesc()
            ->take(self::LIMIT);

        if ($coViewedCounts->isEmpty()) {
            return collect();
        }

        $listings = Listing::active()
            ->whereIn('id', $coViewedCounts->keys())
            ->with(['seller:id,name,is_verified,avatar_path', 'category:id,name'])
            ->get()
            ->keyBy('id');

        return $coViewedCounts->keys()->map(fn (int $id) => $listings->get($id))->filter()->values();
    }
}
