<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * All rating eligibility is enforced here, not just hidden by a frontend
 * button, per the rules: the rater must genuinely be a participant in a
 * completed, dispute-free transaction, may rate it only once, and the person
 * being rated is always computed server-side as "the other participant" -
 * never accepted from client input, so it cannot be swapped by a crafted request.
 */
class RatingService
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    /**
     * Whether $rater may currently rate this transaction at all (used to decide
     * whether to show the rating prompt in the UI).
     */
    public function canRate(Transaction $transaction, User $rater): bool
    {
        try {
            $this->assertEligible($transaction, $rater);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function hasRated(Transaction $transaction, User $rater): bool
    {
        return Rating::where('transaction_id', $transaction->id)->where('rater_id', $rater->id)->exists();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function rate(Transaction $transaction, User $rater, int $stars, ?string $comment = null): Rating
    {
        $this->assertEligible($transaction, $rater);

        if ($stars < Rating::MIN_STARS || $stars > Rating::MAX_STARS) {
            throw new InvalidArgumentException('Please choose a rating between 1 and 5 stars.');
        }

        $ratedUserId = $transaction->isBuyer($rater) ? $transaction->seller_id : $transaction->buyer_id;

        $rating = DB::transaction(fn () => Rating::create([
            'transaction_id' => $transaction->id,
            'rater_id' => $rater->id,
            'rated_id' => $ratedUserId,
            'stars' => $stars,
            'comment' => filled($comment) ? trim($comment) : null,
        ]));

        $this->auditLogger->recordAction(
            $rater,
            'RATING_SUBMITTED',
            'Rating',
            (string) $rating->id,
            ['transaction_id' => $transaction->id, 'rated_id' => $ratedUserId, 'stars' => $stars]
        );

        return $rating;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertEligible(Transaction $transaction, User $rater): void
    {
        if (! $transaction->isBuyer($rater) && ! $transaction->isSeller($rater)) {
            throw new InvalidArgumentException('You did not take part in this transaction.');
        }

        if (strtoupper((string) $transaction->status) !== 'COMPLETED') {
            throw new InvalidArgumentException('You can only rate a transaction once it has been completed.');
        }

        $dispute = $transaction->dispute;

        if ($dispute && ! str_starts_with(strtoupper((string) $dispute->status), 'RESOLVED')) {
            throw new InvalidArgumentException('This transaction has an unresolved dispute and cannot be rated yet.');
        }

        if ($this->hasRated($transaction, $rater)) {
            throw new InvalidArgumentException('You have already rated this transaction.');
        }
    }
}
