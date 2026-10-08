<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\Message;
use App\Models\SearchLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything a signed-in student sees about their own sales. Scoped to
 * exactly one seller's own data, except demandHints() which is a market-wide
 * signal (no other student's personal data involved) shown to help a
 * seller decide what to list next.
 */
class SellerInsightsService
{
    /**
     * @param  string  $period  one of PeriodBoundary::OPTIONS - 'all' by default
     * @return array{items_sold: int, total_earned: float, average_price: float, average_days_to_sale: ?float, average_rating: ?float}
     */
    public function totals(User $seller, string $period = 'all'): array
    {
        return InsightsCache::remember("seller:{$seller->id}:totals:{$period}", function () use ($seller, $period) {
            $sales = $this->completedSales($seller, $period)->get(['amount', 'created_at', 'completed_at']);
            $itemsSold = $sales->count();
            $totalEarned = (float) $sales->sum('amount');

            $daysToSale = $sales
                ->filter(fn (Transaction $t) => $t->completed_at !== null)
                ->map(fn (Transaction $t) => $t->created_at->diffInDays($t->completed_at));

            return [
                'items_sold' => $itemsSold,
                'total_earned' => round($totalEarned, 2),
                'average_price' => $itemsSold > 0 ? round($totalEarned / $itemsSold, 2) : 0.0,
                'average_days_to_sale' => $daysToSale->isNotEmpty() ? round($daysToSale->avg(), 1) : null,
                'average_rating' => $seller->averageRating(),
            ];
        });
    }

    /**
     * @return array<int, array{month: string, total: float}>
     */
    public function earningsByMonth(User $seller): array
    {
        return InsightsCache::remember("seller:{$seller->id}:earnings-by-month", function () use ($seller) {
            $rows = $this->completedSales($seller)
                ->where('completed_at', '>=', now()->subMonths(11)->startOfMonth())
                ->get(['amount', 'completed_at'])
                ->groupBy(fn (Transaction $t) => $t->completed_at->format('Y-m'));

            return collect(range(11, 0))->map(function (int $monthsAgo) use ($rows) {
                $month = now()->subMonths($monthsAgo)->format('Y-m');

                return ['month' => $month, 'total' => round((float) ($rows->get($month)?->sum('amount') ?? 0), 2)];
            })->all();
        });
    }

    /**
     * Percentage of this seller's buyers by year of study, by school, and by
     * gender - three separate single-dimension breakdowns, never cross-
     * tabulated together. Suppressed (empty, with a reason) if fewer than
     * the minimum distinct buyers have year/school set. Gender is always
     * collected (it defaults to 'undisclosed' rather than being left
     * blank), so by_gender uses the same buyer set and threshold check.
     *
     * @return array{by_year: array<int, array{year: int, percentage: float}>, by_school: array<int, array{school: string, percentage: float}>, by_gender: array<int, array{gender: string, percentage: float}>, suppressed: bool}
     */
    public function buyersByYearAndSchool(User $seller): array
    {
        return InsightsCache::remember("seller:{$seller->id}:buyers-by-year-school", function () use ($seller) {
            $buyers = $this->completedSales($seller)
                ->join('users', 'users.id', '=', 'transactions.buyer_id')
                ->whereNotNull('users.year_of_study')
                ->whereNotNull('users.school')
                ->select('users.id as buyer_id', 'users.year_of_study', 'users.school', 'users.gender')
                ->get();

            if ($buyers->pluck('buyer_id')->unique()->count() < (int) config('insights.min_group_size')) {
                return ['by_year' => [], 'by_school' => [], 'by_gender' => [], 'suppressed' => true];
            }

            $total = $buyers->count();

            $byYear = $buyers->groupBy('year_of_study')
                ->map(fn (Collection $g, $year) => ['year' => (int) $year, 'percentage' => round(($g->count() / $total) * 100, 1)])
                ->sortBy('year')
                ->values()
                ->all();

            $bySchool = $buyers->groupBy('school')
                ->map(fn (Collection $g, $school) => ['school' => config('zut.schools.'.$school, $school), 'percentage' => round(($g->count() / $total) * 100, 1)])
                ->sortByDesc('percentage')
                ->values()
                ->all();

            $byGender = $buyers->groupBy('gender')
                ->map(fn (Collection $g, $gender) => ['gender' => ucfirst((string) $gender), 'percentage' => round(($g->count() / $total) * 100, 1)])
                ->sortByDesc('percentage')
                ->values()
                ->all();

            return ['by_year' => $byYear, 'by_school' => $bySchool, 'by_gender' => $byGender, 'suppressed' => false];
        });
    }

    /**
     * Items sold by category, with the highest-selling one flagged as
     * trending. category_id is the category snapshotted at transaction
     * time, not a live join - see the migration that added it.
     *
     * @param  string  $period  one of PeriodBoundary::OPTIONS - 'all' by default
     * @return array<int, array{category: string, total_items: int, amount: float, is_trending: bool}>
     */
    public function soldByCategory(User $seller, string $period = 'all'): array
    {
        return InsightsCache::remember("seller:{$seller->id}:sold-by-category:{$period}", function () use ($seller, $period) {
            $sales = $this->completedSales($seller, $period)->with('category')->get();

            if ($sales->isEmpty()) {
                return [];
            }

            $rows = $sales
                ->groupBy(fn (Transaction $t) => $t->category?->name ?? 'Other')
                ->map(fn (Collection $group, string $category) => [
                    'category' => $category,
                    'total_items' => $group->count(),
                    'amount' => round((float) $group->sum('amount'), 2),
                ])
                ->sortByDesc('total_items')
                ->values();

            return $rows->map(fn (array $row, int $index) => [...$row, 'is_trending' => $index === 0])->all();
        });
    }

    /**
     * How many of this seller's listings turned into a view, then an
     * enquiry (first message from a prospective buyer), then a sale -
     * built from the existing listing_views, messages and transactions
     * tables rather than a new, competing event log.
     *
     * @param  string  $period  one of PeriodBoundary::OPTIONS - 'all' by default
     * @return array{views: int, enquiries: int, sales: int}
     */
    public function funnel(User $seller, string $period = 'all'): array
    {
        return InsightsCache::remember("seller:{$seller->id}:funnel:{$period}", function () use ($seller, $period) {
            $start = PeriodBoundary::start($period);
            $listingIds = $seller->listings()->pluck('id');

            $views = ListingView::whereIn('listing_id', $listingIds)
                ->when($start, fn ($q, $s) => $q->where('viewed_at', '>=', $s))
                ->count();

            // Grouped in PHP rather than a multi-column COUNT(DISTINCT ...)
            // aggregate, which SQLite and MySQL don't agree on the syntax for.
            $enquiries = Message::whereIn('listing_id', $listingIds)
                ->where('sender_id', '!=', $seller->id)
                ->when($start, fn ($q, $s) => $q->where('created_at', '>=', $s))
                ->select('listing_id', 'sender_id')
                ->groupBy('listing_id', 'sender_id')
                ->get()
                ->count();

            $sales = $this->completedSales($seller, $period)->count();

            return ['views' => $views, 'enquiries' => $enquiries, 'sales' => $sales];
        });
    }

    /**
     * @return Collection<int, array{listing: Listing, views: int, reservations: int, conversion_rate: ?float, days_listed: int}>
     */
    public function perListingStats(User $seller): Collection
    {
        return $seller->listings()
            ->withCount(['views', 'reservations'])
            ->get()
            ->map(fn (Listing $listing) => [
                'listing' => $listing,
                'views' => $listing->views_count,
                'reservations' => $listing->reservations_count,
                'conversion_rate' => $listing->views_count > 0 ? round(($listing->reservations_count / $listing->views_count) * 100, 1) : null,
                'days_listed' => $listing->created_at->diffInDays(now()),
            ]);
    }

    /**
     * @return array{fastest: ?array{listing: Listing, days: int}, slowest: ?array{listing: Listing, days: int}}
     */
    public function fastestAndSlowestMoving(User $seller): array
    {
        $sales = $this->completedSales($seller)->with('listing')->get()
            ->filter(fn (Transaction $t) => $t->listing !== null && $t->completed_at !== null)
            ->map(fn (Transaction $t) => ['listing' => $t->listing, 'days' => $t->created_at->diffInDays($t->completed_at)]);

        if ($sales->isEmpty()) {
            return ['fastest' => null, 'slowest' => null];
        }

        return [
            'fastest' => $sales->sortBy('days')->first(),
            'slowest' => $sales->sortByDesc('days')->first(),
        ];
    }

    /**
     * For each of the seller's active listings, compares its price with the
     * median sold price for the same category and condition - only when at
     * least `min_group_size` comparable sales exist.
     *
     * @return Collection<int, array{listing: Listing, median: ?float, difference_percentage: ?float}>
     */
    public function priceCheck(User $seller): Collection
    {
        return $seller->listings()->active()->with('category')->get()->map(function (Listing $listing) {
            $comparablePrices = Transaction::query()
                ->where('transactions.status', 'COMPLETED')
                ->join('listings', 'listings.id', '=', 'transactions.listing_id')
                ->where('listings.category_id', $listing->category_id)
                ->where('listings.condition', $listing->condition)
                ->pluck('transactions.amount')
                ->map(fn ($amount) => (float) $amount)
                ->sort()
                ->values();

            if ($comparablePrices->count() < (int) config('insights.min_group_size')) {
                return ['listing' => $listing, 'median' => null, 'difference_percentage' => null];
            }

            $median = $this->median($comparablePrices);

            return [
                'listing' => $listing,
                'median' => round($median, 2),
                'difference_percentage' => $median > 0 ? round((((float) $listing->price - $median) / $median) * 100, 1) : null,
            ];
        });
    }

    /**
     * Categories where interest (reservations or searches) is high but
     * active supply is low - a market-wide signal, not personal data about
     * any other student.
     *
     * @return array<int, array{category: string, active_listings: int, reservations: int, searches: int}>
     */
    public function demandHints(): array
    {
        return InsightsCache::remember('seller:demand-hints', function () {
            return Category::query()
                ->withCount([
                    'listings as active_listings_count' => fn ($q) => $q->where('status', Listing::STATUS_ACTIVE),
                    'listings as reservations_count' => fn ($q) => $q->whereHas('reservations', fn ($r) => $r->active()),
                ])
                ->get()
                ->map(fn ($category) => [
                    'category' => $category->name,
                    'active_listings' => $category->active_listings_count,
                    'reservations' => $category->reservations_count,
                    'searches' => SearchLog::where('created_at', '>=', now()->subDays(30))
                        ->where('term', 'like', '%'.strtolower(explode(' ', $category->name)[0]).'%')
                        ->count(),
                ])
                ->filter(fn (array $row) => $row['active_listings'] < 5 && ($row['reservations'] > 0 || $row['searches'] > 0))
                ->sortByDesc(fn (array $row) => $row['reservations'] + $row['searches'])
                ->values()
                ->all();
        });
    }

    private function median(Collection $sorted): float
    {
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($sorted->get($middle - 1) + $sorted->get($middle)) / 2;
        }

        return $sorted->get($middle);
    }

    /**
     * @return Builder<Transaction>
     */
    private function completedSales(User $seller, string $period = 'all')
    {
        return Transaction::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'COMPLETED')
            ->when(PeriodBoundary::start($period), fn ($query, $start) => $query->where('completed_at', '>=', $start));
    }
}
