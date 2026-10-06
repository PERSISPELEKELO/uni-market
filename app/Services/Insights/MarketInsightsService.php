<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Category;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\Rating;
use App\Models\Reservation;
use App\Models\SearchLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Campus-wide aggregates for administrators and the Governance Committee.
 * Every breakdown that names a group of students (year of study, school) is
 * suppressed below config('insights.min_group_size') distinct students -
 * see Breakdown suppression in each method below.
 */
class MarketInsightsService
{
    /**
     * @var array<string, int>
     */
    private const PERIOD_DAYS = [
        '7d' => 7,
        '30d' => 30,
        'semester' => 120,
        '12mo' => 365,
    ];

    /**
     * @return array{active_listings: int, completed_transactions: int, total_value: float, active_buyers: int, active_sellers: int, change: array<string, float>}
     */
    public function totals(string $period = '30d'): array
    {
        return InsightsCache::remember("market:totals:{$period}", function () use ($period) {
            [$start, $end, $previousStart, $previousEnd] = $this->periodBoundaries($period);

            $current = $this->totalsForRange($start, $end);
            $previous = $this->totalsForRange($previousStart, $previousEnd);

            return [
                'active_listings' => Listing::where('status', Listing::STATUS_ACTIVE)->count(),
                'completed_transactions' => $current['transactions'],
                'total_value' => $current['value'],
                'active_buyers' => $current['buyers'],
                'active_sellers' => $current['sellers'],
                'change' => [
                    'transactions' => $this->percentChange($previous['transactions'], $current['transactions']),
                    'value' => $this->percentChange($previous['value'], $current['value']),
                    'buyers' => $this->percentChange($previous['buyers'], $current['buyers']),
                    'sellers' => $this->percentChange($previous['sellers'], $current['sellers']),
                ],
            ];
        });
    }

    /**
     * @return array<int, array{week: string, value: float, transactions: int}>
     */
    public function valueOverTime(string $period = '30d'): array
    {
        return InsightsCache::remember("market:value-over-time:{$period}", function () use ($period) {
            [$start, $end] = $this->periodBoundaries($period);

            $rows = Transaction::query()
                ->where('status', 'COMPLETED')
                ->whereBetween('completed_at', [$start, $end])
                ->get(['amount', 'completed_at'])
                ->groupBy(fn (Transaction $t) => $t->completed_at->startOfWeek()->format('Y-m-d'));

            $weeks = collect();
            $cursor = $start->copy()->startOfWeek();

            while ($cursor->lessThanOrEqualTo($end)) {
                $key = $cursor->format('Y-m-d');
                $weekRows = $rows->get($key, collect());

                $weeks->push([
                    'week' => $key,
                    'value' => round((float) $weekRows->sum('amount'), 2),
                    'transactions' => $weekRows->count(),
                ]);

                $cursor->addWeek();
            }

            return $weeks->all();
        });
    }

    /**
     * Heatmap data: completed-purchase counts per (year of study) x
     * (category), suppressed cell by cell.
     *
     * @return array{years: array<int, int>, categories: array<int, string>, cells: array<int, array<int, int|null>>}
     */
    public function buyersByYearAndCategory(string $period = '30d'): array
    {
        return InsightsCache::remember("market:year-x-category:{$period}", function () use ($period) {
            return $this->heatmap($period, 'year_of_study');
        });
    }

    /**
     * @return array{schools: array<int, string>, categories: array<int, string>, cells: array<int, array<int, int|null>>}
     */
    public function buyersBySchoolAndCategory(string $period = '30d'): array
    {
        return InsightsCache::remember("market:school-x-category:{$period}", function () use ($period) {
            $raw = $this->heatmap($period, 'school');

            return [
                'schools' => array_map(fn ($key) => config('zut.schools.'.$key, (string) $key), $raw['years']),
                'categories' => $raw['categories'],
                'cells' => $raw['cells'],
            ];
        });
    }

    /**
     * @return array{years?: array<int, int|string>, categories: array<int, string>, cells: array<int, array<int, int|null>>}
     */
    private function heatmap(string $period, string $groupColumn): array
    {
        [$start, $end] = $this->periodBoundaries($period);

        $categories = Category::orderBy('name')->pluck('name', 'id');

        $rows = Transaction::query()
            ->where('transactions.status', 'COMPLETED')
            ->whereBetween('transactions.completed_at', [$start, $end])
            ->join('users', 'users.id', '=', 'transactions.buyer_id')
            ->join('listings', 'listings.id', '=', 'transactions.listing_id')
            ->whereNotNull("users.{$groupColumn}")
            ->selectRaw("users.{$groupColumn} as group_value, listings.category_id, count(distinct transactions.buyer_id) as buyers, count(*) as total")
            ->groupBy('group_value', 'listings.category_id')
            ->get();

        $groupValues = $rows->pluck('group_value')->unique()->sort()->values();
        $minGroupSize = (int) config('insights.min_group_size');

        $cells = $groupValues->map(function ($groupValue) use ($rows, $categories, $minGroupSize) {
            return $categories->keys()->map(function ($categoryId) use ($rows, $groupValue, $minGroupSize) {
                $match = $rows->first(fn ($r) => $r->group_value == $groupValue && $r->category_id === $categoryId);

                if (! $match || $match->buyers < $minGroupSize) {
                    return null;
                }

                return (int) $match->total;
            })->all();
        })->all();

        return [
            'years' => $groupValues->all(),
            'categories' => $categories->values()->all(),
            'cells' => $cells,
        ];
    }

    /**
     * @return array{categories: array<int, array{category: string, median_days: float}>, items: array<int, array{title: string, days: int}>}
     */
    public function fastestSelling(string $period = '30d'): array
    {
        return InsightsCache::remember("market:fastest-selling:{$period}", function () use ($period) {
            [$start, $end] = $this->periodBoundaries($period);

            $sales = Transaction::query()
                ->where('status', 'COMPLETED')
                ->whereBetween('completed_at', [$start, $end])
                ->with('listing.category')
                ->get()
                ->filter(fn (Transaction $t) => $t->listing !== null)
                ->map(fn (Transaction $t) => [
                    'category' => $t->listing->category?->name ?? 'Other',
                    'title' => $t->listing->title,
                    'days' => $t->created_at->diffInDays($t->completed_at),
                ]);

            $categories = $sales->groupBy('category')
                ->map(fn (Collection $g, string $category) => ['category' => $category, 'median_days' => $this->median($g->pluck('days')->sort()->values())])
                ->sortBy('median_days')
                ->values()
                ->all();

            $items = $sales->sortBy('days')->take(10)->map(fn (array $s) => ['title' => $s['title'], 'days' => $s['days']])->values()->all();

            return ['categories' => $categories, 'items' => $items];
        });
    }

    /**
     * @return array<int, array{category: string, active_listings: int, reservations: int, searches: int}>
     */
    public function supplyVsDemand(): array
    {
        return app(SellerInsightsService::class)->demandHints();
    }

    /**
     * @return array<int, array{term: string, searches: int, average_results: float}>
     */
    public function unmetSearches(int $limit = 15): array
    {
        return InsightsCache::remember("market:unmet-searches:{$limit}", function () use ($limit) {
            return SearchLog::query()
                ->where('created_at', '>=', now()->subDays(60))
                ->selectRaw('term, count(*) as searches, avg(results_count) as average_results')
                ->groupBy('term')
                ->having('average_results', '<', 3)
                ->orderByDesc('searches')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => ['term' => $row->term, 'searches' => (int) $row->searches, 'average_results' => round((float) $row->average_results, 1)])
                ->all();
        });
    }

    /**
     * @return array{views: int, reservations: int, sales: int, view_to_reservation: float, reservation_to_sale: float}
     */
    public function listingFunnel(string $period = '30d'): array
    {
        return InsightsCache::remember("market:funnel:{$period}", function () use ($period) {
            [$start, $end] = $this->periodBoundaries($period);

            $views = ListingView::whereBetween('viewed_at', [$start, $end])->count();
            $reservations = Reservation::whereBetween('created_at', [$start, $end])->count();
            $sales = Transaction::where('status', 'COMPLETED')->whereBetween('completed_at', [$start, $end])->count();

            return [
                'views' => $views,
                'reservations' => $reservations,
                'sales' => $sales,
                'view_to_reservation' => $views > 0 ? round(($reservations / $views) * 100, 1) : 0.0,
                'reservation_to_sale' => $reservations > 0 ? round(($sales / $reservations) * 100, 1) : 0.0,
            ];
        });
    }

    /**
     * @return array{days: array<int, array{day: string, total: int}>, hours: array<int, array{hour: int, total: int}>}
     */
    public function peakTimes(string $period = '30d'): array
    {
        return InsightsCache::remember("market:peak-times:{$period}", function () use ($period) {
            [$start, $end] = $this->periodBoundaries($period);

            $completions = Transaction::where('status', 'COMPLETED')->whereBetween('completed_at', [$start, $end])->pluck('completed_at');

            $days = $completions->groupBy(fn (Carbon $date) => $date->format('l'))
                ->map(fn (Collection $g, string $day) => ['day' => $day, 'total' => $g->count()])
                ->sortByDesc('total')
                ->values()
                ->all();

            $hours = $completions->groupBy(fn (Carbon $date) => $date->format('H'))
                ->map(fn (Collection $g, string $hour) => ['hour' => (int) $hour, 'total' => $g->count()])
                ->sortByDesc('total')
                ->values()
                ->all();

            return ['days' => $days, 'hours' => $hours];
        });
    }

    /**
     * @return array{dispute_rate: float, dispute_rate_by_category: array<int, array{category: string, rate: float}>, average_resolution_days: ?float, verification_approval_rate: ?float, average_seller_rating: ?float, ai_agreement_rate: ?float}
     */
    public function trust(string $period = '30d'): array
    {
        return InsightsCache::remember("market:trust:{$period}", function () use ($period) {
            [$start, $end] = $this->periodBoundaries($period);

            $transactions = Transaction::whereBetween('created_at', [$start, $end])->with('listing.category', 'dispute')->get();
            $totalTransactions = $transactions->count();
            $disputed = $transactions->filter(fn (Transaction $t) => $t->dispute !== null);

            $disputeRateByCategory = $transactions->groupBy(fn (Transaction $t) => $t->listing?->category?->name ?? 'Other')
                ->map(fn (Collection $g, string $category) => [
                    'category' => $category,
                    'rate' => $g->count() > 0 ? round(($g->filter(fn (Transaction $t) => $t->dispute !== null)->count() / $g->count()) * 100, 1) : 0.0,
                ])
                ->values()
                ->all();

            $resolvedDisputes = Dispute::whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['resolved_buyer', 'resolved_seller'])
                ->get();

            $resolutionDays = $resolvedDisputes->map(fn (Dispute $d) => $d->created_at->diffInDays($d->updated_at));

            $verificationTotal = User::whereBetween('student_verification_submitted_at', [$start, $end])->count();
            $verificationApproved = User::whereBetween('student_verification_submitted_at', [$start, $end])
                ->where('student_verification_status', User::STUDENT_VERIFICATION_VERIFIED)
                ->count();

            return [
                'dispute_rate' => $totalTransactions > 0 ? round(($disputed->count() / $totalTransactions) * 100, 1) : 0.0,
                'dispute_rate_by_category' => $disputeRateByCategory,
                'average_resolution_days' => $resolutionDays->isNotEmpty() ? round($resolutionDays->avg(), 1) : null,
                'verification_approval_rate' => $verificationTotal > 0 ? round(($verificationApproved / $verificationTotal) * 100, 1) : null,
                'average_seller_rating' => round((float) (Rating::where('status', Rating::STATUS_VISIBLE)->avg('stars') ?? 0), 1) ?: null,
                'ai_agreement_rate' => $this->aiAgreementRate($resolvedDisputes),
            ];
        });
    }

    /**
     * Percentage of resolved disputes where the AI's suggested resolution
     * and the admin's actual decision were on the same side. REFUND and
     * PARTIAL_REFUND_OR_RETURN count as "favoured the buyer"; everything
     * else (PAYMENT_VERIFICATION, MANUAL_REVIEW) is treated as not clearly
     * buyer-favoured, so it only agrees with a seller-favoured decision.
     * A simplification, documented here rather than hidden.
     *
     * @param  Collection<int, Dispute>  $resolvedDisputes
     */
    private function aiAgreementRate(Collection $resolvedDisputes): ?float
    {
        $withAiSuggestion = $resolvedDisputes->filter(fn (Dispute $d) => $d->ai_suggested_resolution !== null);

        if ($withAiSuggestion->isEmpty()) {
            return null;
        }

        $buyerFavouredTokens = ['REFUND', 'PARTIAL_REFUND_OR_RETURN'];

        $agreements = $withAiSuggestion->filter(function (Dispute $d) use ($buyerFavouredTokens) {
            $aiFavouredBuyer = in_array($d->ai_suggested_resolution, $buyerFavouredTokens, true);
            $decisionFavouredBuyer = $d->status === 'resolved_buyer';

            return $aiFavouredBuyer === $decisionFavouredBuyer;
        });

        return round(($agreements->count() / $withAiSuggestion->count()) * 100, 1);
    }

    /**
     * @return array{transactions: int, value: float, buyers: int, sellers: int}
     */
    private function totalsForRange(Carbon $start, Carbon $end): array
    {
        $completed = Transaction::where('status', 'COMPLETED')->whereBetween('completed_at', [$start, $end]);

        return [
            'transactions' => $completed->count(),
            'value' => (float) $completed->sum('amount'),
            'buyers' => (clone $completed)->distinct()->count('buyer_id'),
            'sellers' => (clone $completed)->distinct()->count('seller_id'),
        ];
    }

    private function percentChange(float $previous, float $current): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon} [start, end, previousStart, previousEnd]
     */
    private function periodBoundaries(string $period): array
    {
        $days = self::PERIOD_DAYS[$period] ?? self::PERIOD_DAYS['30d'];

        $end = now();
        $start = now()->subDays($days);
        $previousEnd = $start->copy();
        $previousStart = $start->copy()->subDays($days);

        return [$start, $end, $previousStart, $previousEnd];
    }

    private function median(Collection $sorted): float
    {
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? round(($sorted->get($middle - 1) + $sorted->get($middle)) / 2, 1)
            : round($sorted->get($middle), 1);
    }
}
