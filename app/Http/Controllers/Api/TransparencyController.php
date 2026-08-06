<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AppealStatus;
use App\Http\Controllers\Controller;
use App\Models\Appeal;
use App\Models\AuditLog;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;

class TransparencyController extends Controller
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    /**
     * Public route returning non-PII aggregated governance metrics.
     * GET /api/v1/transparency/metrics
     */
    public function metrics(): JsonResponse
    {
        // 1. Moderation actions count (past 30 days)
        $moderationActionsCount = AuditLog::where('timestamp', '>=', now()->subDays(30))->count();

        // 2. Appeals submitted vs overturn percentage
        $totalAppeals = Appeal::count();
        $overturnedAppeals = Appeal::where('status', AppealStatus::OVERTURNED->value)
            ->orWhere('status', AppealStatus::OVERTURNED)
            ->count();

        $overturnPercentage = $totalAppeals > 0
            ? round(($overturnedAppeals / $totalAppeals) * 100, 2)
            : 0.0;

        // 3. Real-time audit chain integrity status (valid: true/false)
        $integrityResult = $this->auditLogger->verifyIntegrity();

        return response()->json([
            'status' => 'success',
            'data' => [
                'moderation_actions_count_30d' => $moderationActionsCount,
                'appeals_statistics' => [
                    'total_submitted' => $totalAppeals,
                    'overturned_count' => $overturnedAppeals,
                    'overturn_percentage' => $overturnPercentage,
                ],
                'audit_chain_integrity' => [
                    'valid' => $integrityResult['is_valid'],
                    'failed_at_id' => $integrityResult['failed_at_id'],
                ],
            ],
        ]);
    }
}
