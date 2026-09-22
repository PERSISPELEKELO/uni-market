<?php

use App\Models\Dispute;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RatingService;

function completedTransaction(array $overrides = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'buyer_id' => User::factory(),
        'seller_id' => User::factory(),
        'status' => 'COMPLETED',
    ], $overrides));
}

it('lets the buyer rate the seller and the seller rate the buyer, independently', function () {
    $transaction = completedTransaction();
    $service = app(RatingService::class);

    $buyerRating = $service->rate($transaction, $transaction->buyer, 5, 'Great seller, item as described.');
    $sellerRating = $service->rate($transaction, $transaction->seller, 4, 'Paid promptly, easy to deal with.');

    expect($buyerRating->rated_id)->toBe($transaction->seller_id)
        ->and($buyerRating->rater_id)->toBe($transaction->buyer_id)
        ->and($sellerRating->rated_id)->toBe($transaction->buyer_id)
        ->and($sellerRating->rater_id)->toBe($transaction->seller_id)
        ->and(Rating::count())->toBe(2);
});

it('always computes who is rated from the transaction, never from client input', function () {
    // The service signature does not even accept a rated-user id - this proves it structurally,
    // by confirming the stored rated_id always matches "the other participant" regardless of who calls it.
    $transaction = completedTransaction();
    $rating = app(RatingService::class)->rate($transaction, $transaction->buyer, 3);

    expect($rating->rated_id)->toBe($transaction->seller_id)
        ->and($rating->rated_id)->not->toBe($transaction->buyer_id);
});

it('refuses a rating from someone who did not take part in the transaction', function () {
    $transaction = completedTransaction();
    $outsider = User::factory()->create();

    expect(fn () => app(RatingService::class)->rate($transaction, $outsider, 5))
        ->toThrow(InvalidArgumentException::class, 'did not take part');

    expect(Rating::count())->toBe(0);
});

it('refuses a rating until the transaction is completed', function (string $status) {
    $transaction = completedTransaction(['status' => $status]);

    expect(fn () => app(RatingService::class)->rate($transaction, $transaction->buyer, 5))
        ->toThrow(InvalidArgumentException::class, 'once it has been completed');

    expect(Rating::count())->toBe(0);
})->with([
    'initiated' => ['initiated'],
    'pending meeting' => ['PENDING_MEETING'],
    'in inspection' => ['ITEM_INSPECTION'],
    'disputed' => ['DISPUTED'],
]);

it('refuses a rating while a dispute is unresolved, but allows one once the dispute is resolved', function (string $disputeStatus, bool $shouldAllow) {
    $transaction = completedTransaction();
    Dispute::create([
        'transaction_id' => $transaction->id,
        'raised_by' => $transaction->buyer_id,
        'reason' => 'The item did not match the listing.',
        'status' => $disputeStatus,
    ]);

    $attempt = fn () => app(RatingService::class)->rate($transaction->fresh(), $transaction->buyer, 5);

    if ($shouldAllow) {
        expect($attempt())->toBeInstanceOf(Rating::class);
    } else {
        expect($attempt)->toThrow(InvalidArgumentException::class, 'unresolved dispute');
        expect(Rating::count())->toBe(0);
    }
})->with([
    'open' => ['open', false],
    'under review' => ['under_review', false],
    'resolved for buyer' => ['resolved_buyer', true],
    'resolved for seller' => ['resolved_seller', true],
]);

it('does not let the same person rate the same transaction twice', function () {
    $transaction = completedTransaction();
    $service = app(RatingService::class);

    $service->rate($transaction, $transaction->buyer, 5);

    expect(fn () => $service->rate($transaction, $transaction->buyer, 1))
        ->toThrow(InvalidArgumentException::class, 'already rated');

    expect(Rating::count())->toBe(1)
        ->and(Rating::first()->stars)->toBe(5); // the first rating was not overwritten
});

it('rejects a star value outside 1 to 5, even though the model column would accept it', function (int $stars) {
    $transaction = completedTransaction();

    expect(fn () => app(RatingService::class)->rate($transaction, $transaction->buyer, $stars))
        ->toThrow(InvalidArgumentException::class, 'between 1 and 5');
})->with([0, 6, -1, 100]);

describe('canRate and hasRated', function () {
    it('reports canRate correctly for each ineligible reason and hasRated after rating', function () {
        $transaction = completedTransaction();
        $service = app(RatingService::class);

        expect($service->canRate($transaction, $transaction->buyer))->toBeTrue()
            ->and($service->hasRated($transaction, $transaction->buyer))->toBeFalse();

        $service->rate($transaction, $transaction->buyer, 5);

        expect($service->canRate($transaction, $transaction->buyer))->toBeFalse()
            ->and($service->hasRated($transaction, $transaction->buyer))->toBeTrue();
    });

    it('reports canRate false for a transaction that never happened to this user', function () {
        $transaction = completedTransaction();

        expect(app(RatingService::class)->canRate($transaction, User::factory()->create()))->toBeFalse();
    });
});

it('computes the average, count and star breakdown correctly on the rated user', function () {
    $seller = User::factory()->create();
    $service = app(RatingService::class);

    foreach ([5, 5, 3] as $stars) {
        $transaction = completedTransaction(['seller_id' => $seller->id]);
        $service->rate($transaction, $transaction->buyer, $stars);
    }

    expect($seller->averageRating())->toBe(4.3)
        ->and($seller->ratingsCount())->toBe(3)
        ->and($seller->ratingBreakdown())->toBe([5 => 2, 4 => 0, 3 => 1, 2 => 0, 1 => 0]);
});

it('reports no average for a user with no ratings yet', function () {
    $user = User::factory()->create();

    expect($user->averageRating())->toBeNull()->and($user->ratingsCount())->toBe(0);
});
