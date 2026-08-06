<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AppealStatus;
use App\Http\Controllers\Controller;
use App\Models\Appeal;
use App\Models\Listing;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppealController extends Controller
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    /**
     * Submit a new appeal for a moderated listing or account within 7 days.
     * POST /api/v1/appeals
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('create', Appeal::class);

        $validated = $request->validate([
            'target_type' => ['required', 'string', 'max:50'],
            'target_id' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:5'],
            'evidence_urls' => ['nullable', 'array'],
            'evidence_urls.*' => ['url'],
        ]);

        // Check 7-day limit if target listing exists
        if (in_array(strtolower($validated['target_type']), ['listing', 'app\models\listing'], true)) {
            $listing = Listing::find($validated['target_id']);
            if ($listing && $listing->created_at->diffInDays(now()) > 7) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Appeals must be submitted within 7 days of item moderation or publication.',
                ], 422);
            }
        }

        $appeal = Appeal::create([
            'user_id' => $user->id,
            'target_type' => $validated['target_type'],
            'target_id' => (string) $validated['target_id'],
            'reason' => $validated['reason'],
            'evidence_urls' => $validated['evidence_urls'] ?? [],
            'status' => AppealStatus::PENDING,
        ]);

        $this->auditLogger->recordAction(
            $user,
            'APPEAL_SUBMITTED',
            'Appeal',
            (string) $appeal->id,
            [
                'target_type' => $appeal->target_type,
                'target_id' => $appeal->target_id,
                'reason' => $appeal->reason,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Appeal submitted successfully.',
            'data' => $appeal,
        ], 201);
    }

    /**
     * Paginated review queue for the Governance Committee.
     * GET /api/v1/governance/appeals
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Appeal::class);

        $perPage = min((int) $request->query('per_page', 15), 100);
        $appeals = Appeal::orderBy('id', 'desc')->paginate($perPage);

        $sanitizedItems = collect($appeals->items())->map(function (Appeal $appeal) {
            $anonId = 'STUDENT_ANON_' . str_pad((string) $appeal->user_id, 3, '0', STR_PAD_LEFT);
            return [
                'id' => $appeal->id,
                'anonymized_submitter_id' => $anonId,
                'target_type' => $appeal->target_type,
                'target_id' => $appeal->target_id,
                'reason' => $appeal->reason,
                'evidence_urls' => $appeal->evidence_urls,
                'status' => $appeal->status instanceof AppealStatus ? $appeal->status->value : (string) $appeal->status,
                'governance_notes' => $appeal->governance_notes,
                'created_at' => $appeal->created_at?->toIso8601String(),
                'resolved_at' => $appeal->resolved_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $sanitizedItems,
            'meta' => [
                'current_page' => $appeals->currentPage(),
                'last_page' => $appeals->lastPage(),
                'per_page' => $appeals->perPage(),
                'total' => $appeals->total(),
            ],
        ]);
    }

    /**
     * Resolve an appeal with UPHELD or OVERTURNED decision.
     * POST /api/v1/governance/appeals/{appeal}/resolve
     */
    public function resolve(Request $request, Appeal $appeal): JsonResponse
    {
        Gate::authorize('resolve', $appeal);

        $validated = $request->validate([
            'outcome' => ['required', 'string', 'in:UPHELD,OVERTURNED'],
            'governance_notes' => ['nullable', 'string'],
        ]);

        $outcomeStr = strtoupper($validated['outcome']);
        $newStatus = $outcomeStr === 'OVERTURNED' ? AppealStatus::OVERTURNED : AppealStatus::UPHELD;

        $appeal->update([
            'status' => $newStatus,
            'governance_notes' => $validated['governance_notes'] ?? null,
            'resolved_at' => now(),
        ]);

        // If OVERTURNED, automatically restore target resource (e.g. mark listing active)
        if ($outcomeStr === 'OVERTURNED') {
            if (in_array(strtolower($appeal->target_type), ['listing', 'app\models\listing'], true)) {
                $listing = Listing::find($appeal->target_id);
                if ($listing) {
                    $listing->update(['status' => 'active']);
                }
            }
        }

        $this->auditLogger->recordAction(
            $request->user(),
            'APPEAL_RESOLVED',
            'Appeal',
            (string) $appeal->id,
            [
                'outcome' => $outcomeStr,
                'governance_notes' => $validated['governance_notes'] ?? null,
                'target_restored' => $outcomeStr === 'OVERTURNED',
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => "Appeal resolved as {$outcomeStr}.",
            'data' => $appeal,
        ]);
    }
}
