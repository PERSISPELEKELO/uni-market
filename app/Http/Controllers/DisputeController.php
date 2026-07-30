<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Dispute;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class DisputeController extends Controller
{
    public function store(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $transaction->update(['status' => 'disputed']);

        // 1. Call Python NLP microservice for sentiment & dispute evaluation
        $aiAnalysis = [
            'ai_sentiment_score' => null,
            'ai_confidence_score' => null,
            'ai_suggested_resolution' => null,
            'ai_analysis_summary' => null,
        ];

        try {
            // Replace port with the Python service address (e.g. FastAPI / Flask)
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
            // Fallback gracefully if Python microservice is offline
            \Log::warning('AI Service offline: ' . $e->getMessage());
        }

        // 2. Save dispute with AI analysis
        $dispute = Dispute::create(array_merge([
            'transaction_id' => $transaction->id,
            'raised_by' => Auth::id(),
            'reason' => $validated['reason'],
            'status' => 'open',
        ], $aiAnalysis));

        // 3. Record Audit Log
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DISPUTE_RAISED',
            'entity_type' => Dispute::class,
            'entity_id' => $dispute->id,
            'payload' => $dispute->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Dispute raised. AI moderation has processed the initial claims.');
    }
}