<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Asks the Python NLP microservice to analyse a dispute report.
 *
 * The AI result is advisory only: any failure (service down, slow, malformed or
 * out-of-range answer) returns empty values so the dispute is still recorded and
 * left for a human moderator.
 */
class DisputeAnalysisService
{
    /**
     * @return array{ai_sentiment_score: float|null, ai_confidence_score: float|null, ai_suggested_resolution: string|null, ai_analysis_summary: string|null}
     */
    public function analyze(Transaction $transaction, string $reason): array
    {
        $empty = [
            'ai_sentiment_score' => null,
            'ai_confidence_score' => null,
            'ai_suggested_resolution' => null,
            'ai_analysis_summary' => null,
        ];

        if (! config('services.dispute_ai.enabled')) {
            return $empty;
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.dispute_ai.timeout'))
                ->post(config('services.dispute_ai.url').'/api/analyze-dispute', [
                    'transaction_id' => $transaction->id,
                    'dispute_reason' => $reason,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('AI dispute analysis unavailable: '.$exception->getMessage());

            return $empty;
        }

        if (! $response->successful()) {
            Log::warning('AI dispute analysis returned HTTP '.$response->status());

            return $empty;
        }

        $sentiment = $response->json('sentiment_score');
        $confidence = $response->json('confidence_score');

        if (! is_numeric($sentiment) || ! is_numeric($confidence)
            || $sentiment < -1 || $sentiment > 1 || $confidence < 0 || $confidence > 1) {
            Log::warning('AI dispute analysis returned out-of-range or malformed scores.');

            return $empty;
        }

        return [
            'ai_sentiment_score' => round((float) $sentiment, 2),
            'ai_confidence_score' => round((float) $confidence, 2),
            'ai_suggested_resolution' => $this->cleanText($response->json('suggested_resolution'), 100),
            'ai_analysis_summary' => $this->cleanText($response->json('summary'), 1000),
        ];
    }

    private function cleanText(mixed $value, int $limit): ?string
    {
        return is_string($value) && trim($value) !== '' ? Str::limit(trim(strip_tags($value)), $limit, '') : null;
    }
}
