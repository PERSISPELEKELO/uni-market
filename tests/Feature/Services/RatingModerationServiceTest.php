<?php

use App\Models\AuditLog;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ReviewModerated;
use App\Services\RatingModerationService;
use App\Services\RatingService;
use DomainException;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->service = app(RatingModerationService::class));

function ratedTransaction(): Rating
{
    $transaction = Transaction::factory()->create([
        'buyer_id' => User::factory(),
        'seller_id' => User::factory(),
        'status' => 'COMPLETED',
    ]);

    return app(RatingService::class)->rate($transaction, $transaction->buyer, 5, 'Great seller.');
}

it('hides a visible review, excluding it from the rated user\'s average and count', function () {
    Notification::fake();
    $rating = ratedTransaction();
    $admin = User::factory()->create(['role' => 'admin']);
    $seller = $rating->rated;

    expect($seller->ratingsCount())->toBe(1);

    $this->service->hide($rating, $admin, 'Violates platform rules.');

    expect($rating->fresh()->isHidden())->toBeTrue()
        ->and($seller->ratingsCount())->toBe(0)
        ->and($seller->averageRating())->toBeNull()
        ->and(AuditLog::where('action', 'RATING_HIDDEN')->where('target_id', $rating->id)->exists())->toBeTrue();

    Notification::assertSentTo($rating->rater, ReviewModerated::class);
});

it('refuses to hide a review that is already hidden', function () {
    $rating = ratedTransaction();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->service->hide($rating, $admin, 'First hide.');

    expect(fn () => $this->service->hide($rating->fresh(), $admin, 'Second hide.'))
        ->toThrow(DomainException::class, 'already hidden');
});

it('restores a hidden review, bringing it back into the average and count', function () {
    Notification::fake();
    $rating = ratedTransaction();
    $admin = User::factory()->create(['role' => 'admin']);
    $seller = $rating->rated;
    $this->service->hide($rating, $admin, 'Testing.');

    $this->service->restore($rating->fresh(), $admin);

    expect($rating->fresh()->isHidden())->toBeFalse()
        ->and($seller->ratingsCount())->toBe(1)
        ->and(AuditLog::where('action', 'RATING_RESTORED')->where('target_id', $rating->id)->exists())->toBeTrue();

    Notification::assertSentTo($rating->rater, ReviewModerated::class);
});

it('refuses to restore a review that is not hidden', function () {
    $rating = ratedTransaction();
    $admin = User::factory()->create(['role' => 'admin']);

    expect(fn () => $this->service->restore($rating, $admin))
        ->toThrow(DomainException::class, 'not hidden');
});
