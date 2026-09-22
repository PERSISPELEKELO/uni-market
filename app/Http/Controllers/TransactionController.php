<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\HandoverVerificationService;
use App\Services\InspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function __construct(
        protected HandoverVerificationService $handoverService,
        protected InspectionService $inspectionService
    ) {}

    // Verify handover OTP (Seller Action)
    public function verifyHandover(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('verifyHandover', $transaction);

        if ($transaction->handover_attempts >= 5) {
            return back()->with('error', 'Maximum handover verification attempts exceeded.');
        }

        $validated = $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $submittedOtp = trim((string) $validated['otp']);

        try {
            $this->handoverService->verifyHandoverCode($transaction, $submittedOtp, Auth::user());

            return back()->with('success', 'Handover confirmed! 48-hour item inspection window is now active.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // Complete transaction (Buyer Action, after the handover has been verified)
    public function complete(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('complete', $transaction);

        try {
            $this->inspectionService->confirmItemAcceptance($transaction, Auth::user());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction completed. The item is now marked as sold.');
    }
}
