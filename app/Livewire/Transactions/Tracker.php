<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Exceptions\DisputeWindowExpiredException;
use App\Models\Dispute;
use App\Models\Transaction;
use App\Services\AuditLoggerService;
use App\Services\HandoverVerificationService;
use App\Services\InspectionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class Tracker extends Component
{
    public ?int $selectedTransactionId = null;
    public bool $showDisputeModal = false;
    public string $disputeReason = '';
    public string $handoverOtp = '';

    public function mount(?Transaction $transaction = null): void
    {
        if ($transaction && $transaction->exists) {
            $this->selectedTransactionId = $transaction->id;
        } else {
            $first = Transaction::where('buyer_id', Auth::id())
                ->orWhere('seller_id', Auth::id())
                ->latest()
                ->first();
            $this->selectedTransactionId = $first?->id;
        }
    }

    public function selectTransaction(int $transactionId): void
    {
        $this->selectedTransactionId = $transactionId;
        $this->showDisputeModal = false;
        $this->disputeReason = '';
        $this->handoverOtp = '';
    }

    public function verifyHandoverOtp(int $transactionId): void
    {
        $tx = Transaction::findOrFail($transactionId);

        if (Auth::id() !== $tx->seller_id) {
            session()->flash('error', 'Only the seller can verify the handover code.');
            return;
        }

        $this->validate([
            'handoverOtp' => 'required|string|size:6',
        ]);

        try {
            app(HandoverVerificationService::class)->verifyHandoverCode($tx, $this->handoverOtp, Auth::user());
            $this->handoverOtp = '';
            session()->flash('success', 'Handover verified successfully! 48-hour post-purchase inspection period started.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markCompleted(int $transactionId): void
    {
        $tx = Transaction::with(['buyer', 'seller', 'listing'])->findOrFail($transactionId);

        if (Auth::id() !== $tx->buyer_id && Auth::id() !== $tx->seller_id) {
            session()->flash('error', 'Unauthorized action.');
            return;
        }

        try {
            if (Auth::id() === $tx->buyer_id && in_array(strtoupper($tx->status), ['ITEM_INSPECTION', 'HANDED_OVER'], true)) {
                app(InspectionService::class)->confirmItemAcceptance($tx, Auth::user());
            } else {
                $completedAt = now();
                $tx->update([
                    'status' => 'COMPLETED',
                    'completed_at' => $completedAt,
                ]);
                if ($tx->listing) {
                    $tx->listing->update(['status' => 'sold']);
                }

                app(AuditLoggerService::class)->recordAction(
                    Auth::user(),
                    'TRANSACTION_COMPLETED',
                    'Transaction',
                    (string) $tx->id,
                    ['status' => 'COMPLETED', 'completed_at' => $completedAt->toIso8601String()]
                );
            }

            session()->flash('success', 'Transaction marked as COMPLETED! Escrow funds released.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openDisputeModal(): void
    {
        $tx = Transaction::find($this->selectedTransactionId);
        if (!$tx) {
            return;
        }

        $status = strtoupper($tx->status);
        $inspectionEnd = $tx->inspection_expires_at ?? $tx->inspection_ends_at;

        if (!in_array($status, ['ITEM_INSPECTION', 'HANDED_OVER'], true) || !$inspectionEnd || now()->greaterThan($inspectionEnd)) {
            session()->flash('error', 'Dispute window not active or expired. Disputes can only be raised during active item inspection.');
            return;
        }

        $this->showDisputeModal = true;
    }

    public function closeDisputeModal(): void
    {
        $this->showDisputeModal = false;
        $this->disputeReason = '';
    }

    public function submitDispute(): void
    {
        $this->validate([
            'disputeReason' => 'required|string|min:10',
        ]);

        $tx = Transaction::findOrFail($this->selectedTransactionId);

        if (Auth::id() !== $tx->buyer_id) {
            session()->flash('error', 'Only the buyer can raise a post-purchase dispute.');
            return;
        }

        try {
            app(InspectionService::class)->raisePostPurchaseDispute($tx, Auth::user(), [
                'reason' => $this->disputeReason,
            ]);

            $this->showDisputeModal = false;
            $this->disputeReason = '';

            session()->flash('success', 'Dispute submitted successfully. Governance moderation review initiated.');
        } catch (DisputeWindowExpiredException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $currentUserId = Auth::id();

        $userTransactions = Transaction::with(['buyer', 'seller', 'listing', 'dispute'])
            ->where('buyer_id', $currentUserId)
            ->orWhere('seller_id', $currentUserId)
            ->latest()
            ->get();

        $activeTransaction = null;
        if ($this->selectedTransactionId) {
            $activeTransaction = Transaction::with(['buyer', 'seller', 'listing', 'dispute.reporter'])
                ->find($this->selectedTransactionId);
        }

        return view('livewire.transactions.tracker', [
            'transactions' => $userTransactions,
            'activeTransaction' => $activeTransaction,
        ])->layout('layouts.app', ['title' => 'Transaction Escrow Tracker - UniMarket']);
    }
}
