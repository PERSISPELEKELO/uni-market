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
     * Export asymmetrically signed reputation and activity data for the authenticated user.
     */
    public function export(Request $request): JsonResponse
    {
        $exportPackage = $this->exporterService->exportUserData($request->user());

        return response()->json($exportPackage, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="reputation-export-user-' . $request->user()->id . '.json"',
        ]);
    }

    /**
     * Verify an asymmetrically signed reputation data export payload.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'data' => ['required', 'array'],
            'signature' => ['required', 'string'],
            'public_key' => ['required', 'string'],
        ]);

        $isValid = $this->exporterService->verifyExportSignature($request->all());

        return response()->json([
            'status' => 'success',
            'is_valid' => $isValid,
            'message' => $isValid ? 'Asymmetric signature is authentic and verified.' : 'Signature verification failed. Data may be tampered or invalid.',
        ]);
    }
}
