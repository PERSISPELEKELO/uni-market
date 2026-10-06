<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Simple market-basket association analysis over categories, from
 * completed purchases per buyer.
 *
 * support(A, B)    = buyers who bought both A and B / all buyers
 * confidence(A, B) = buyers who bought both A and B / buyers who bought A
 * lift(A, B)       = confidence(A, B) / support(B)
 *
 * A pair is only ever shown when at least config('insights.min_group_size')
 * distinct buyers bought both sides of it.
 */
class FrequentlyBoughtTogetherService
{
    /**
     * @return Collection<int, array{category_a: string, category_b: string, support: float, confidence: float, lift: float, buyers: int}>
     */
    public function topPairs(int $limit = 10): Collection
    {
        return InsightsCache::remember("fbt:top-pairs:{$limit}", function () use ($limit) {
            $buyerCategories = $this->buyerCategoryMap();

            return $this->pairsFromMap($buyerCategories)->sortByDesc('lift')->take($limit)->values();
        });
    }

    /**
     * Pairs involving the given category only - used on a listing page as
     * "students who bought items in {category} also bought...".
     *
     * @return Collection<int, array{category_a: string, category_b: string, support: float, confidence: float, lift: float, buyers: int}>
     */
    public function forCategory(string $categorySlug, int $limit = 5): Collection
    {
        return InsightsCache::remember("fbt:for-category:{$categorySlug}:{$limit}", function () use ($categorySlug, $limit) {
            $category = Category::where('slug', $categorySlug)->first();

            if (! $category) {
                return collect();
            }

            $buyerCategories = $this->buyerCategoryMap();

            return $this->pairsFromMap($buyerCategories)
                ->filter(fn (array $pair) => $pair['category_a'] === $category->name || $pair['category_b'] === $category->name)
                ->sortByDesc('lift')
                ->take($limit)
                ->values();
        });
    }

    /**
     * @param  Collection<int, Collection<int, string>>  $buyerCategories  buyer_id => distinct category names they bought from
     * @return Collection<int, array{category_a: string, category_b: string, support: float, confidence: float, lift: float, buyers: int}>
     */
    private function pairsFromMap(Collection $buyerCategories): Collection
    {
        $totalBuyers = $buyerCategories->count();
        $minGroupSize = (int) config('insights.min_group_size');

        if ($totalBuyers < $minGroupSize) {
            return collect();
        }

        // How many distinct buyers bought from each single category - needed for confidence/support.
        $buyersPerCategory = [];
        foreach ($buyerCategories as $categories) {
            foreach ($categories as $category) {
                $buyersPerCategory[$category] = ($buyersPerCategory[$category] ?? 0) + 1;
            }
        }

        // How many distinct buyers bought from each pair of categories.
        $buyersPerPair = [];
        foreach ($buyerCategories as $categories) {
            $unique = $categories->unique()->values()->all();
            sort($unique);

            foreach ($this->combinations($unique) as [$a, $b]) {
                $key = "{$a}|||{$b}";
                $buyersPerPair[$key] = ($buyersPerPair[$key] ?? 0) + 1;
            }
        }

        return collect($buyersPerPair)
            ->filter(fn (int $buyers) => $buyers >= $minGroupSize)
            ->map(function (int $buyers, string $key) use ($totalBuyers, $buyersPerCategory) {
                [$a, $b] = explode('|||', $key);

                $support = $buyers / $totalBuyers;
                $confidenceAtoB = $buyers / $buyersPerCategory[$a];
                $supportB = $buyersPerCategory[$b] / $totalBuyers;

                return [
                    'category_a' => $a,
                    'category_b' => $b,
                    'support' => round($support * 100, 1),
                    'confidence' => round($confidenceAtoB * 100, 1),
                    'lift' => $supportB > 0 ? round($confidenceAtoB / $supportB, 2) : 0.0,
                    'buyers' => $buyers,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, Collection<int, string>> buyer_id => distinct category names bought
     */
    private function buyerCategoryMap(): Collection
    {
        return Transaction::query()
            ->where('transactions.status', 'COMPLETED')
            ->join('listings', 'listings.id', '=', 'transactions.listing_id')
            ->join('categories', 'categories.id', '=', 'listings.category_id')
            ->select('transactions.buyer_id', 'categories.name as category')
            ->get()
            ->groupBy('buyer_id')
            ->map(fn (Collection $rows) => $rows->pluck('category')->unique()->values());
    }

    /**
     * @param  array<int, string>  $items  already sorted, so each unordered pair appears once
     * @return array<int, array{0: string, 1: string}>
     */
    private function combinations(array $items): array
    {
        $pairs = [];

        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                $pairs[] = [$items[$i], $items[$j]];
            }
        }

        return $pairs;
    }
}
