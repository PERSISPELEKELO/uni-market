<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Transaction;
use App\Services\AuditLoggerService;
use App\Services\HandoverVerificationService;
use App\Services\InspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class TransactionController extends Controller
{
    public function __construct(
        protected HandoverVerificationService $handoverService,
        protected InspectionService $inspectionService
    ) {}

    // Initiate purchase request
    public function initiate(Request $request, Listing $listing): RedirectResponse
    {
        if ($listing->isOwnedBy(Auth::user())) {
            return back()->with('error', 'You cannot purchase your own listing.');
        }

        $plainOtp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashed = Hash::make($plainOtp);

        $transaction = DB::transaction(function () use ($listing, $plainOtp, $hashed): ?Transaction {
            $lockedListing = Listing::whereKey($listing->id)->lockForUpdate()->first();

            if (! $lockedListing || $lockedListing->status !== Listing::STATUS_ACTIVE) {
                return null;
            }

            $transaction = Transaction::create([
                'listing_id' => $lockedListing->id,
                'buyer_id' => Auth::id(),
                'seller_id' => $lockedListing->user_id,
                'amount' => $lockedListing->price,
                'status' => 'PENDING_MEETING',
                'handover_otp_hash' => $hashed,
                'handover_otp_plain' => $plainOtp,
                'handover_code_hash' => $hashed,
                'handover_code_plain' => $plainOtp,
                'handover_code_expires_at' => now()->addDays(3),
                'handover_attempts' => 0,
            ]);

            $lockedListing->update(['status' => Listing::STATUS_PENDING]);

            return $transaction;
        });

        if (! $transaction) {
            return back()->with('error', 'Sorry, this item is no longer available.');
        }

        app(AuditLoggerService::class)->recordAction(
            Auth::user(),
            'TRANSACTION_INITIATED',
            'Transaction',
            (string) $transaction->id,
            $transaction->makeHidden(['handover_code_hash', 'handover_code_plain', 'handover_otp_hash', 'handover_otp_plain'])->toArray()
        );

        return redirect()->route('transactions.tracker', ['transaction' => $transaction->id])
            ->with('success', 'Purchase reserved! Show your 6-digit Campus Handoff Code to the seller at the meet-up.');
    }

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
