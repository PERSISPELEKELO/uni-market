<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\DisputeWindowExpiredException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\HandoverVerificationService;
use App\Services\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionHandoverController extends Controller
{
    public function __construct(
        protected HandoverVerificationService $handoverService,
        protected InspectionService $inspectionService
    ) {}

    /**
     * Get active handover 6-digit code (Buyer Only).
     * GET /api/v1/transactions/{transaction}/handover-code
     */
    public function getHandoverCode(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $transaction->buyer_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action. Only the buyer can view the handover code.',
            ], 403);
        }

        $code = $transaction->handover_code_plain;
        if (!$code) {
            $code = $this->handoverService->generateHandoverCode($transaction);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'handover_code' => $code,
                'expires_at' => $transaction->handover_code_expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Verify handover code at meet-up (Seller Only).
     * POST /api/v1/transactions/{transaction}/verify-handover
     */
    public function verifyHandover(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $transaction->seller_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action. Only the seller can verify the handover code.',
            ], 403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $this->handoverService->verifyHandoverCode($transaction, $validated['code'], $user);

            return response()->json([
                'status' => 'success',
                'message' => 'Handover code verified successfully. Post-purchase inspection window started.',
                'data' => [
                    'status' => $transaction->status,
                    'handed_over_at' => $transaction->handed_over_at?->toIso8601String(),
                    'inspection_ends_at' => $transaction->inspection_ends_at?->toIso8601String(),
                    'inspection_period_hours' => $transaction->inspection_period_hours,
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Raise a post-purchase dispute during active inspection window (Buyer Only).
     * POST /api/v1/transactions/{transaction}/dispute
     */
    public function raiseDispute(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $transaction->buyer_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action. Only the buyer can raise a post-purchase dispute.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
            'evidence_urls' => ['nullable', 'array'],
            'evidence_urls.*' => ['url'],
        ]);

        try {
            $dispute = $this->inspectionService->raisePostPurchaseDispute($transaction, $user, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Post-purchase dispute raised successfully.',
                'data' => [
                    'dispute_id' => $dispute->id,
                    'transaction_status' => $transaction->fresh()->status,
                    'reason' => $dispute->reason,
                ],
            ], 201);
        } catch (DisputeWindowExpiredException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm early item acceptance by buyer (Buyer Only).
     * POST /api/v1/transactions/{transaction}/accept
     */
    public function acceptItem(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $transaction->buyer_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action. Only the buyer can confirm item acceptance.',
            ], 403);
        }

        try {
            $this->inspectionService->confirmItemAcceptance($transaction, $user);

            return response()->json([
                'status' => 'success',
                'message' => 'Item accepted and transaction completed successfully.',
                'data' => [
                    'status' => $transaction->fresh()->status,
                    'completed_at' => $transaction->fresh()->completed_at?->toIso8601String(),
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
