<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Services\AppealWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppealController extends Controller
{
    public function __construct(
        protected AppealWorkflowService $workflowService
    ) {}

    /**
     * Display a listing of appeals for the authenticated user or committee.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isGovernanceCommittee() || $user->isAdmin()) {
            $query = Appeal::with('user')->latest();
            if ($request->has('status')) {
                $query->where('status', $request->query('status'));
            }
            $appeals = $query->get();
        } else {
            $appeals = $user->appeals()->latest()->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => $appeals,
        ]);
    }

    /**
     * Submit a new appeal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', 'string', 'max:50'],
            'target_id' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string'],
            'evidence_urls' => ['nullable', 'array'],
            'evidence_urls.*' => ['url'],
        ]);

        $appeal = $this->workflowService->submitAppeal(
            $request->user(),
            $validated['target_type'],
            $validated['target_id'],
            $validated['reason'],
            $validated['evidence_urls'] ?? []
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Appeal submitted successfully.',
            'data' => $appeal,
        ], 201);
    }

    /**
     * Display the specified appeal.
     */
    public function show(Appeal $appeal): JsonResponse
    {
        Gate::authorize('view', $appeal);

        return response()->json([
            'status' => 'success',
            'data' => $appeal->load('user'),
        ]);
    }

    /**
     * Transition appeal status to UNDER_REVIEW.
     */
    public function startReview(Appeal $appeal): JsonResponse
    {
        Gate::authorize('review', $appeal);

        $updatedAppeal = $this->workflowService->startReview($appeal, request()->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Appeal review started.',
            'data' => $updatedAppeal,
        ]);
    }

    /**
     * Decide/Resolve an appeal (UPHELD or OVERTURNED).
     */
    public function decide(Request $request, Appeal $appeal): JsonResponse
    {
        Gate::authorize('decide', $appeal);

        $validated = $request->validate([
            'outcome' => ['required', 'string', 'in:UPHELD,OVERTURNED'],
            'governance_notes' => ['nullable', 'string'],
        ]);

        $resolvedAppeal = $this->workflowService->decideAppeal(
            $appeal,
            $validated['outcome'],
            $validated['governance_notes'] ?? null,
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Appeal decision recorded: {$validated['outcome']}.",
            'data' => $resolvedAppeal,
        ]);
    }
}
