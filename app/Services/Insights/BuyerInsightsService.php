<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything a signed-in student sees about their own purchases. Every
 * method here is scoped to exactly one user's own data - see
 * MarketInsightsService for anything that aggregates across other students.
 */
class BuyerInsightsService
{
    /**
     * @return array{total_items: int, total_spent: float, average_spend: float}
     */
    public function totals(User $buyer): array
    {
        return InsightsCache::remember("buyer:{$buyer->id}:totals", function () use ($buyer) {
            $purchases = $this->completedPurchases($buyer);
            $totalItems = $purchases->count();
            $totalSpent = (float) $purchases->sum('amount');

            return [
                'total_items' => $totalItems,
                'total_spent' => round($totalSpent, 2),
                'average_spend' => $totalItems > 0 ? round($totalSpent / $totalItems, 2) : 0.0,
            ];
        });
    }

    /**
     * @return array<int, array{month: string, total: float}> oldest to newest, 12 months
     */
    public function spendingByMonth(User $buyer): array
    {
        return InsightsCache::remember("buyer:{$buyer->id}:spending-by-month", function () use ($buyer) {
            $rows = $this->completedPurchases($buyer)
                ->where('completed_at', '>=', now()->subMonths(11)->startOfMonth())
                ->get(['amount', 'completed_at'])
                ->groupBy(fn (Transaction $t) => $t->completed_at->format('Y-m'));

            return $this->last12Months()->map(fn (string $month) => [
                'month' => $month,
                'total' => round((float) ($rows->get($month)?->sum('amount') ?? 0), 2),
            ])->all();
        });
    }

    /**
     * @return array<int, array{category: string, amount: float, percentage: float}>
     */
    public function spendingByCategory(User $buyer): array
    {
        return InsightsCache::remember("buyer:{$buyer->id}:spending-by-category", function () use ($buyer) {
            $purchases = $this->completedPurchases($buyer)->with('listing.category')->get();
            $total = (float) $purchases->sum('amount');

            if ($total <= 0) {
                return [];
            }

            return $purchases
                ->groupBy(fn (Transaction $t) => $t->listing?->category?->name ?? 'Other')
                ->map(fn (Collection $group, string $category) => [
                    'category' => $category,
                    'amount' => round((float) $group->sum('amount'), 2),
                    'percentage' => round(((float) $group->sum('amount') / $total) * 100, 1),
                ])
                ->sortByDesc('amount')
                ->values()
                ->all();
        });
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function purchaseHistory(User $buyer): Collection
    {
        return $this->completedPurchases($buyer)
            ->with(['listing', 'seller', 'ratings' => fn ($q) => $q->where('rater_id', $buyer->id)])
            ->latest('completed_at')
            ->get();
    }

    /**
     * Top categories/items bought recently by students in the same year AND
     * school, falling back to year-only, then the whole campus, if the
     * narrower group is below the privacy threshold. Always says which
     * level was actually used.
     *
     * @return array{level: string, categories: array<int, array{name: string, total: int}>}
     */
    public function popularWithStudentsLikeYou(User $buyer): array
    {
        return InsightsCache::remember("buyer:{$buyer->id}:popular-like-you", function () use ($buyer) {
            if ($buyer->hasCompletedZutProfile()) {
                $sameYearAndSchool = $this->topCategoriesForGroup(
                    fn ($q) => $q->where('year_of_study', $buyer->year_of_study)->where('school', $buyer->school),
                    $buyer->id
                );

                if ($sameYearAndSchool !== null) {
                    return ['level' => 'students in your year and school', 'categories' => $sameYearAndSchool];
                }

                $sameYear = $this->topCategoriesForGroup(fn ($q) => $q->where('year_of_study', $buyer->year_of_study), $buyer->id);

                if ($sameYear !== null) {
                    return ['level' => 'students in your year', 'categories' => $sameYear];
                }
            }

            return ['level' => 'the whole campus', 'categories' => $this->topCategoriesForGroup(fn ($q) => $q, $buyer->id) ?? []];
        });
    }

    /**
     * $excludeBuyerId is always the requesting student themselves: the
     * privacy threshold is about anonymity among *other* students, so the
     * viewer never counts toward their own group's size, and their own
     * purchases never appear in what is shown back to them as "others".
     *
     * @param  \Closure(Builder<User>): Builder<User>  $scopeBuyers
     * @return array<int, array{name: string, total: int}>|null null if the matching group of distinct buyers is below the privacy threshold
     */
    private function topCategoriesForGroup(\Closure $scopeBuyers, int $excludeBuyerId): ?array
    {
        $buyerIds = $scopeBuyers(User::query())->where('id', '!=', $excludeBuyerId)->pluck('id');

        if ($buyerIds->count() < (int) config('insights.min_group_size')) {
            return null;
        }

        $distinctBuyers = Transaction::query()
            ->whereIn('buyer_id', $buyerIds)
            ->where('transactions.status', 'COMPLETED')
            ->where('completed_at', '>=', now()->subDays(60))
            ->distinct()
            ->pluck('buyer_id');

        if ($distinctBuyers->count() < (int) config('insights.min_group_size')) {
            return null;
        }

        return Transaction::query()
            ->whereIn('buyer_id', $buyerIds)
            ->where('transactions.status', 'COMPLETED')
            ->where('completed_at', '>=', now()->subDays(60))
            ->join('listings', 'listings.id', '=', 'transactions.listing_id')
            ->join('categories', 'categories.id', '=', 'listings.category_id')
            ->selectRaw('categories.name as name, count(*) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * @return Builder<Transaction>
     */
    private function completedPurchases(User $buyer)
    {
        return Transaction::query()->where('buyer_id', $buyer->id)->where('status', 'COMPLETED');
    }

    /**
     * @return Collection<int, string> the 12 "Y-m" month keys ending this month, oldest first
     */
    private function last12Months(): Collection
    {
        return collect(range(11, 0))->map(fn (int $monthsAgo) => now()->subMonths($monthsAgo)->format('Y-m'));
    }
}
