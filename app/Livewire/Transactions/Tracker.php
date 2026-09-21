<?php

declare(strict_types=1);

namespace App\Livewire\Transactions;

use App\Models\Transaction;
use App\Services\HandoverVerificationService;
use App\Services\InspectionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Tracker extends Component
{
    use AuthorizesRequests;

    /**
     * Locked so the browser cannot swap in another user's transaction id.
     */
    #[Locked]
    public ?int $selectedTransactionId = null;

    public bool $showDisputeModal = false;

    public string $disputeReason = '';

    public string $handoverOtp = '';

    public function mount(?Transaction $transaction = null): void
    {
        if ($transaction && $transaction->exists) {
            $this->authorize('view', $transaction);
            $this->selectedTransactionId = $transaction->id;

            return;
        }

        $this->selectedTransactionId = Transaction::forParticipant(Auth::id())->latest()->value('id');
    }

    public function selectTransaction(int $transactionId): void
    {
        $transaction = Transaction::findOrFail($transactionId);
        $this->authorize('view', $transaction);

        $this->selectedTransactionId = $transaction->id;
        $this->showDisputeModal = false;
        $this->disputeReason = '';
        $this->handoverOtp = '';
    }

    public function verifyHandoverOtp(): void
    {
        $transaction = $this->selectedTransaction();

        if (! $transaction || ! $this->passesPolicy('verifyHandover', $transaction)) {
            return;
        }

        $this->validate(
            ['handoverOtp' => ['required', 'string', 'size:6']],
            [
                'handoverOtp.required' => "Please enter the buyer's 6-digit code.",
                'handoverOtp.size' => 'The handover code must be exactly 6 digits.',
            ]
        );

        try {
            app(HandoverVerificationService::class)->verifyHandoverCode($transaction, $this->handoverOtp, Auth::user());
            $this->handoverOtp = '';
            $this->notify('success', 'Handover verified! The 48-hour inspection period has started.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->notify('error', $exception->getMessage());
        }
    }

    public function markCompleted(): void
    {
        $transaction = $this->selectedTransaction();

        if (! $transaction || ! $this->passesPolicy('complete', $transaction)) {
            return;
        }

        try {
            app(InspectionService::class)->confirmItemAcceptance($transaction, Auth::user());
            $this->notify('success', 'Transaction completed. The item is now marked as sold.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->notify('error', $exception->getMessage());
        }
    }

    public function openDisputeModal(): void
    {
        $transaction = $this->selectedTransaction();

        if (! $transaction || ! $this->passesPolicy('dispute', $transaction)) {
            return;
        }

        $inspectionEnd = $transaction->inspection_expires_at ?? $transaction->inspection_ends_at;

        if (! $transaction->isInInspection() || ! $inspectionEnd || now()->greaterThan($inspectionEnd)) {
            $this->notify('error', 'The dispute window is not active or has expired. Disputes can only be raised during the item inspection period.');

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
        $transaction = $this->selectedTransaction();

        if (! $transaction || ! $this->passesPolicy('dispute', $transaction)) {
            return;
        }

        $this->validate(
            ['disputeReason' => ['required', 'string', 'min:10', 'max:2000']],
            [
                'disputeReason.required' => 'Please describe the problem with the item.',
                'disputeReason.min' => 'Please give a little more detail - at least 10 characters.',
                'disputeReason.max' => 'Please keep your description under 2,000 characters.',
            ]
        );

        try {
            app(InspectionService::class)->raisePostPurchaseDispute($transaction, Auth::user(), [
                'reason' => $this->disputeReason,
            ]);

            $this->showDisputeModal = false;
            $this->disputeReason = '';

            $this->notify('success', 'Dispute submitted. It has been sent for moderation review.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->notify('error', $exception->getMessage());
        }
    }

    public function render()
    {
        $userId = (int) Auth::id();

        $transactions = Transaction::with(['buyer:id,name', 'seller:id,name', 'listing:id,title'])
            ->forParticipant($userId)
            ->latest()
            ->get();

        $activeTransaction = $this->selectedTransaction(['buyer', 'seller', 'listing', 'dispute.reporter']);

        if ($activeTransaction && $this->needsHandoverCode($activeTransaction)) {
            app(HandoverVerificationService::class)->generateHandoverCode($activeTransaction);
            $activeTransaction->refresh();
        }

        return view('livewire.transactions.tracker', [
            'transactions' => $transactions,
            'activeTransaction' => $activeTransaction,
        ])->layout('layouts.app', ['title' => 'My Transactions - UniMarket']);
    }

    /**
     * The currently selected transaction, only if the signed-in user takes part in it.
     *
     * @param  array<int, string>  $relations
     */
    private function selectedTransaction(array $relations = []): ?Transaction
    {
        if (! $this->selectedTransactionId) {
            return null;
        }

        return Transaction::with($relations)
            ->forParticipant((int) Auth::id())
            ->find($this->selectedTransactionId);
    }

    private function needsHandoverCode(Transaction $transaction): bool
    {
        $isPending = in_array(strtoupper((string) $transaction->status), ['PENDING_MEETING', 'INITIATED', 'RESERVED', 'PENDING'], true);

        return $isPending && ! $transaction->handover_otp_plain && ! $transaction->handover_code_plain;
    }

    private function passesPolicy(string $ability, Transaction $transaction): bool
    {
        $response = Gate::inspect($ability, $transaction);

        if ($response->denied()) {
            $this->notify('error', $response->message() ?? 'You are not allowed to do that.');
        }

        return $response->allowed();
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }
}
