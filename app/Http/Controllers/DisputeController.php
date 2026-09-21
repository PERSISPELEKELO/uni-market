<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Transaction;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DisputeController extends Controller
{
    public function store(Request $request, Transaction $transaction)
    {
        Gate::authorize('dispute', $transaction);

        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $status = strtoupper($transaction->status);
        $inspectionEnd = $transaction->inspection_expires_at ?? $transaction->inspection_ends_at;

        // Enforce Rule: Dispute can ONLY be raised during ITEM_INSPECTION mode within inspection window
        if (! in_array($status, ['ITEM_INSPECTION', 'HANDED_OVER'], true) || ! $inspectionEnd || now()->greaterThan($inspectionEnd)) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'Dispute window not active or expired.'], 422);
            }

            return back()->with('error', 'Dispute window not active or expired.');
        }

        $transaction->update(['status' => 'DISPUTED']);

        // 1. Call Python NLP microservice for sentiment & dispute evaluation
        $aiAnalysis = [
            'ai_sentiment_score' => null,
            'ai_confidence_score' => null,
            'ai_suggested_resolution' => null,
            'ai_analysis_summary' => null,
        ];

        try {
            $response = Http::timeout(3)->post('http://127.0.0.1:8000/api/analyze-dispute', [
                'transaction_id' => $transaction->id,
                'dispute_reason' => $validated['reason'],
            ]);

            if ($response->successful()) {
                $aiData = $response->json();
                $aiAnalysis = [
                    'ai_sentiment_score' => $aiData['sentiment_score'] ?? null,
                    'ai_confidence_score' => $aiData['confidence_score'] ?? null,
                    'ai_suggested_resolution' => $aiData['suggested_resolution'] ?? null,
                    'ai_analysis_summary' => $aiData['summary'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('AI Service offline: '.$e->getMessage());
        }

        // 2. Save dispute with AI analysis
        $dispute = Dispute::create(array_merge([
            'transaction_id' => $transaction->id,
            'raised_by' => Auth::id(),
            'reason' => $validated['reason'],
            'status' => 'open',
        ], $aiAnalysis));

        // 3. Record Audit Log
        app(AuditLoggerService::class)->recordAction(
            Auth::user(),
            'DISPUTE_RAISED',
            'Dispute',
            (string) $dispute->id,
            $dispute->toArray()
        );

        return back()->with('success', 'Dispute raised. AI moderation has processed the initial claims.');
    }
}
