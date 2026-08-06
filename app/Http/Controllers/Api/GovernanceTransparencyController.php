<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appeal;
use App\Models\AuditLog;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernanceTransparencyController extends Controller
{
    public function __construct(
        protected AuditLoggerService $auditLoggerService
    ) {}

    /**
     * Get aggregate governance transparency statistics.
     */
    public function summary(): JsonResponse
    {
        $totalAuditLogs = AuditLog::count();
        $chainStatus = $this->auditLoggerService->verifyChainIntegrity();

        $totalAppeals = Appeal::count();
        $pendingAppeals = Appeal::where('status', Appeal::STATUS_PENDING)->count();
        $underReviewAppeals = Appeal::where('status', Appeal::STATUS_UNDER_REVIEW)->count();
        $upheldAppeals = Appeal::where('status', Appeal::STATUS_UPHELD)->count();
        $overturnedAppeals = Appeal::where('status', Appeal::STATUS_OVERTURNED)->count();

        // Calculate average resolution time in hours
        $resolvedAppeals = Appeal::whereNotNull('resolved_at')->get();
        $avgResolutionHours = 0;

        if ($resolvedAppeals->isNotEmpty()) {
            $totalSeconds = $resolvedAppeals->sum(function ($appeal) {
                return $appeal->resolved_at->diffInSeconds($appeal->created_at);
            });
            $avgResolutionHours = round(($totalSeconds / $resolvedAppeals->count()) / 3600, 2);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'timestamp' => now()->toIso8601String(),
                'audit_chain' => [
                    'total_logs' => $totalAuditLogs,
                    'integrity_status' => $chainStatus['status'],
                    'message' => $chainStatus['message'],
                ],
                'appeals_statistics' => [
                    'total_submitted' => $totalAppeals,
                    'pending' => $pendingAppeals,
                    'under_review' => $underReviewAppeals,
                    'upheld' => $upheldAppeals,
                    'overturned' => $overturnedAppeals,
                    'average_resolution_hours' => $avgResolutionHours,
                ],
            ],
        ]);
    }

    /**
     * Get an anonymized public feed of audit log entries.
     */
    public function auditFeed(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $logs = AuditLog::orderBy('id', 'desc')->paginate($perPage);

        $anonymizedItems = collect($logs->items())->map(function (AuditLog $log) {
            return [
                'uuid' => $log->uuid,
                'timestamp' => $log->timestamp?->toIso8601String(),
                'anonymized_actor_hash' => $log->actor_id ? substr(hash('sha256', 'actor_salt_' . $log->actor_id), 0, 16) : 'SYSTEM',
                'actor_role' => $log->actor_role,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'payload' => $this->sanitizePayload($log->payload ?? []),
                'previous_hash' => $log->previous_hash,
                'current_hash' => $log->current_hash,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $anonymizedItems,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Sanitize and scrub PII fields from payload.
     */
    protected function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = ['email', 'phone', 'phone_number', 'student_id', 'name', 'password', 'ip_address', 'address'];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $payload[$key] = '[ANONYMIZED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            }
        }

        return $payload;
    }
}
