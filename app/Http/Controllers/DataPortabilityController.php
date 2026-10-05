<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReputationExporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataPortabilityController extends Controller
{
    public function __construct(
        protected ReputationExporterService $exporterService
    ) {}

    /**
     * Download the authenticated student's own signed reputation export.
     * The student is always taken from the session - never from any
     * client-supplied id - so nobody can request another user's data.
     */
    public function export(Request $request): JsonResponse
    {
        $document = $this->exporterService->exportUserData($request->user());

        return response()->json($document, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="reputation_export_'.now()->format('Y-m-d').'.json"',
        ]);
    }

    /**
     * Programmatic (JSON API) verification of an uploaded export file.
     * Public/unauthenticated on purpose: verifying a credential must not
     * require an account, since the whole point is it stays checkable by
     * anyone (e.g. an employer) after the student has graduated.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $document = json_decode($request->file('file')->get(), true);

        if (! is_array($document)) {
            return response()->json([
                'valid' => false,
                'reason' => 'That file is not valid JSON.',
            ], 422);
        }

        $result = $this->exporterService->verifyExport($document);

        return response()->json([
            'valid' => $result['valid'],
            'reason' => $result['reason'],
        ]);
    }
}
