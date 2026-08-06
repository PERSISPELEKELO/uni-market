<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Transaction;
use App\Services\AuditLoggerService;
use App\Services\HandoverVerificationService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TransactionController extends Controller
{
    public function __construct(
        protected HandoverVerificationService $handoverService
    ) {}

    // Initiate purchase request
    public function initiate(Request $request, Listing $listing)
    {
        if ($listing->user_id === Auth::id()) {
            return back()->with('error', 'You cannot purchase your own listing.');
        }

        $plainOtp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashed = Hash::make($plainOtp);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => Auth::id(),
            'seller_id' => $listing->user_id,
            'amount' => $listing->price,
            'status' => 'PENDING_MEETING',
            'handover_otp_hash' => $hashed,
            'handover_otp_plain' => $plainOtp,
            'handover_code_hash' => $hashed,
            'handover_code_plain' => $plainOtp,
            'handover_code_expires_at' => now()->addDays(3),
            'handover_attempts' => 0,
        ]);

        $listing->update(['status' => 'pending']);

        app(AuditLoggerService::class)->recordAction(
            Auth::user(),
            'TRANSACTION_INITIATED',
            'Transaction',
            (string) $transaction->id,
            $transaction->toArray()
        );

        return redirect()->route('transactions.tracker', ['transaction' => $transaction->id])
            ->with('success', 'Purchase reserved! Show your 6-digit Campus Handoff Code to the seller at the meet-up.');
    }

    public function store(Request $request, Listing $listing)
    {
        return $this->initiate($request, $listing);
    }

    public function show(Transaction $transaction)
    {
        return view('livewire.transactions.tracker', [
            'transaction' => $transaction,
            'isBuyer' => Auth::id() === $transaction->buyer_id,
            'isSeller' => Auth::id() === $transaction->seller_id,
        ]);
    }

    // Verify handover OTP (Seller Action)
    public function verifyHandover(Request $request, Transaction $transaction)
    {
        if (Auth::id() !== $transaction->seller_id) {
            abort(403, 'Unauthorized action.');
        }

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

    // Complete transaction (Buyer Action or Admin)
    public function complete(Request $request, Transaction $transaction)
    {
        if (!in_array(Auth::id(), [$transaction->buyer_id, $transaction->seller_id], true)) {
            abort(403);
        }

        $completedAt = now();
        $transaction->update([
            'status' => 'COMPLETED',
            'completed_at' => $completedAt,
        ]);
        if ($transaction->listing) {
            $transaction->listing->update(['status' => 'sold']);
        }

        app(AuditLoggerService::class)->recordAction(
            Auth::user(),
            'TRANSACTION_COMPLETED',
            'Transaction',
            (string) $transaction->id,
            ['status' => 'COMPLETED', 'completed_at' => $completedAt->toIso8601String()],
        );

        return back()->with('success', 'Escrow transaction fulfilled and completed!');
    }
}