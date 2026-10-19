<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\Listing;
use App\Models\ListingSimilarity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Similar items" - classic TF-IDF (term frequency / inverse document
 * frequency) over each listing's title + description + category name,
 * compared with cosine similarity. Real, explainable information
 * retrieval, not a black box: two listings score highly here only
 * because they genuinely share distinctive words.
 *
 * Results are precomputed into listing_similarities (see that
 * migration's docblock) rather than computed on every page view, so a
 * listing page stays fast regardless of catalogue size.
 */
class ContentSimilarityService
{
    private const TOP_N = 10;

    private const MIN_TOKEN_LENGTH = 3;

    /**
     * Common English words carrying no topical signal - excluding them
     * keeps the vectors focused on words that actually distinguish one
     * listing from another.
     *
     * @var array<int, string>
     */
    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'have',
        'has', 'was', 'were', 'are', 'you', 'all', 'but', 'not', 'its', 'it',
        'can', 'will', 'just', 'very', 'also', 'into', 'out', 'over', 'only',
        'more', 'some', 'any', 'one', 'two', 'our', 'their', 'they', 'them',
        'been', 'being', 'had', 'did', 'does', 'about', 'still', 'than',
        'per', 'few', 'now', 'get', 'got',
    ];

    /**
     * Recomputes every active listing's similarity list in one pass -
     * vectors are built once for the whole catalogue, not once per
     * listing, so this stays reasonable even for a large catalogue. Run
     * nightly by recommendations:refresh-similarity.
     */
    public function refreshAll(): void
    {
        $listings = Listing::active()->with('category')->get();

        if ($listings->count() < 2) {
            ListingSimilarity::query()->delete();

            return;
        }

        $vectors = $this->buildVectors($listings);

        DB::transaction(function () use ($listings, $vectors) {
            ListingSimilarity::query()->delete();

            foreach ($listings as $listing) {
                $this->writeTopMatches($listing->id, $listings, $vectors);
            }
        });
    }

    /**
     * Refreshes just one listing's similarity list - called right after it
     * is created or edited, so "Similar items" doesn't wait for the
     * nightly batch to catch up.
     */
    public function refreshFor(Listing $listing): void
    {
        $candidates = Listing::active()->with('category')->get();

        if (! $candidates->contains('id', $listing->id)) {
            $candidates->push($listing->loadMissing('category'));
        }

        if ($candidates->count() < 2) {
            ListingSimilarity::where('listing_id', $listing->id)->delete();

            return;
        }

        $vectors = $this->buildVectors($candidates);

        DB::transaction(function () use ($listing, $candidates, $vectors) {
            ListingSimilarity::where('listing_id', $listing->id)->delete();
            $this->writeTopMatches($listing->id, $candidates, $vectors);
        });
    }

    /**
     * @param  Collection<int, Listing>  $listings
     * @return array<int, array<string, float>> listing id => term => tf-idf weight
     */
    private function buildVectors(Collection $listings): array
    {
        $documents = $listings->mapWithKeys(fn (Listing $l) => [$l->id => $this->tokenize($this->documentText($l))]);

        $idf = $this->computeIdf($documents);

        return $documents->map(fn (array $tokens) => $this->tfIdfVector($tokens, $idf))->all();
    }

    /**
     * @param  Collection<int, Listing>  $listings
     * @param  array<int, array<string, float>>  $vectors
     */
    private function writeTopMatches(int $listingId, Collection $listings, array $vectors): void
    {
        $targetVector = $vectors[$listingId] ?? [];

        if (empty($targetVector)) {
            return;
        }

        $matches = $listings
            ->filter(fn (Listing $candidate) => $candidate->id !== $listingId)
            ->map(fn (Listing $candidate) => [
                'id' => $candidate->id,
                'score' => $this->cosineSimilarity($targetVector, $vectors[$candidate->id] ?? []),
            ])
            ->filter(fn (array $row) => $row['score'] > 0.0)
            ->sortByDesc('score')
            ->take(self::TOP_N);

        $now = now();

        foreach ($matches as $match) {
            ListingSimilarity::create([
                'listing_id' => $listingId,
                'similar_listing_id' => $match['id'],
                'score' => $match['score'],
                'computed_at' => $now,
            ]);
        }
    }

    private function documentText(Listing $listing): string
    {
        return $listing->title.' '.$listing->description.' '.($listing->category->name ?? '');
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
        $tokens = preg_split('/\s+/', trim($text)) ?: [];

        return array_values(array_filter(
            $tokens,
            fn (string $token) => mb_strlen($token) >= self::MIN_TOKEN_LENGTH && ! in_array($token, self::STOPWORDS, true)
        ));
    }

    /**
     * Smoothed inverse document frequency: rarer words across the
     * catalogue count for more, common ones for less.
     *
     * @param  Collection<int, array<int, string>>  $documents
     * @return array<string, float>
     */
    private function computeIdf(Collection $documents): array
    {
        $documentCount = $documents->count();
        $documentFrequency = [];

        foreach ($documents as $tokens) {
            foreach (array_unique($tokens) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
        }

        $idf = [];
        foreach ($documentFrequency as $term => $count) {
            $idf[$term] = log(($documentCount + 1) / ($count + 1)) + 1;
        }

        return $idf;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, float>  $idf
     * @return array<string, float>
     */
    private function tfIdfVector(array $tokens, array $idf): array
    {
        if (empty($tokens)) {
            return [];
        }

        $termCounts = array_count_values($tokens);
        $totalTerms = count($tokens);

        $vector = [];
        foreach ($termCounts as $term => $count) {
            $termFrequency = $count / $totalTerms;
            $vector[$term] = $termFrequency * ($idf[$term] ?? 0.0);
        }

        return $vector;
    }

    /**
     * @param  array<string, float>  $a
     * @param  array<string, float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        foreach ($a as $term => $weight) {
            if (isset($b[$term])) {
                $dotProduct += $weight * $b[$term];
            }
        }

        $normA = sqrt(array_sum(array_map(fn (float $w) => $w * $w, $a)));
        $normB = sqrt(array_sum(array_map(fn (float $w) => $w * $w, $b)));

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }
}
