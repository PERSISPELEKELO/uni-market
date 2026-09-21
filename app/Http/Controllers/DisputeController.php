<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Transaction;
use App\Services\AuditLoggerService;
use App\Services\DisputeAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DisputeController extends Controller
{
    public function __construct(
        protected DisputeAnalysisService $disputeAnalysis
    ) {}

    public function store(Request $request, Transaction $transaction)
    {
        Gate::authorize('dispute', $transaction);

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:2000',
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

        // The AI analysis is advisory: if the microservice is unavailable the dispute is still recorded.
        $aiAnalysis = $this->disputeAnalysis->analyze($transaction, $validated['reason']);

        $transaction->update(['status' => 'DISPUTED']);

        $dispute = Dispute::create(array_merge([
            'transaction_id' => $transaction->id,
            'raised_by' => Auth::id(),
            'reason' => $validated['reason'],
            'status' => 'open',
        ], $aiAnalysis));

        app(AuditLoggerService::class)->recordAction(
            Auth::user(),
            'DISPUTE_RAISED',
            'Dispute',
            (string) $dispute->id,
            $dispute->toArray()
        );

        return back()->with('success', $dispute->ai_sentiment_score !== null
            ? 'Dispute raised. AI analysis is attached and a moderator will review it.'
            : 'Dispute raised. A moderator will review it.');
    }
}
