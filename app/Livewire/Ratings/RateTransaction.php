<?php

namespace App\Livewire\Ratings;

use App\Models\Rating;
use App\Models\Transaction;
use App\Services\RatingService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Self-contained: renders nothing when the signed-in user cannot rate this
 * transaction, a star-and-comment form when they can, or a short recap once
 * they have. Safe to embed unconditionally (e.g. on the tracker page and in
 * a chat thread) without the parent needing to pre-check eligibility.
 */
class RateTransaction extends Component
{
    public Transaction $transaction;

    public int $stars = 0;

    public string $comment = '';

    public function mount(Transaction $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function submit(RatingService $ratings): void
    {
        $this->validate(
            ['stars' => ['required', 'integer', 'min:1', 'max:5']],
            ['stars.required' => 'Please choose a star rating before submitting.']
        );

        try {
            $ratings->rate($this->transaction, Auth::user(), $this->stars, $this->comment);
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Thanks! Your rating has been submitted.');
    }

    public function render()
    {
        $user = Auth::user();
        $ratings = app(RatingService::class);
        $existing = Rating::where('transaction_id', $this->transaction->id)->where('rater_id', $user->id)->first();
        $ratedUser = $this->transaction->isBuyer($user) ? $this->transaction->seller : $this->transaction->buyer;

        return view('livewire.ratings.rate-transaction', [
            'canRate' => ! $existing && $ratings->canRate($this->transaction, $user),
            'existing' => $existing,
            'ratedUser' => $ratedUser,
        ]);
    }
}
