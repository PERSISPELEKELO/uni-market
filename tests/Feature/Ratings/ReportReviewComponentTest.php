<?php

use App\Livewire\Ratings\ReportReview;
use App\Models\Appeal;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RatingService;
use Livewire\Livewire;

function reviewedRating(): Rating
{
    $transaction = Transaction::factory()->create([
        'buyer_id' => User::factory(),
        'seller_id' => User::factory(),
        'status' => 'COMPLETED',
    ]);

    return app(RatingService::class)->rate($transaction, $transaction->buyer, 2, 'Not a great experience.');
}

it('lets another student report a review, creating an appeal against it', function () {
    $rating = reviewedRating();
    $reporter = User::factory()->create(['role' => 'student']);

    Livewire::actingAs($reporter)->test(ReportReview::class, ['rating' => $rating])
        ->call('toggle')
        ->set('reason', 'spam')
        ->set('details', 'This looks like spam.')
        ->call('submit');

    $appeal = Appeal::where('target_type', 'Rating')->where('target_id', (string) $rating->id)->first();

    expect($appeal)->not->toBeNull()
        ->and($appeal->user_id)->toBe($reporter->id)
        ->and($appeal->reason)->toContain('Spam')
        ->and($appeal->reason)->toContain('This looks like spam.');
});

it('rejects a report submitted without a reason', function () {
    $rating = reviewedRating();
    $reporter = User::factory()->create(['role' => 'student']);

    Livewire::actingAs($reporter)->test(ReportReview::class, ['rating' => $rating])
        ->call('toggle')
        ->call('submit')
        ->assertHasErrors('reason');

    expect(Appeal::where('target_type', 'Rating')->count())->toBe(0);
});

it('does not let the review\'s own author report their own review', function () {
    $rating = reviewedRating();

    Livewire::actingAs($rating->rater)->test(ReportReview::class, ['rating' => $rating])
        ->call('toggle')
        ->set('reason', 'other')
        ->call('submit')
        ->assertDispatched('notify', type: 'error');

    expect(Appeal::where('target_type', 'Rating')->count())->toBe(0);
});

it('blocks a second report from the same user while the first is still pending', function () {
    $rating = reviewedRating();
    $reporter = User::factory()->create(['role' => 'student']);

    Livewire::actingAs($reporter)->test(ReportReview::class, ['rating' => $rating])
        ->call('toggle')->set('reason', 'spam')->call('submit');

    Livewire::actingAs($reporter)->test(ReportReview::class, ['rating' => $rating])
        ->call('toggle')->set('reason', 'other')->call('submit')
        ->assertDispatched('notify', type: 'error');

    expect(Appeal::where('target_type', 'Rating')->where('target_id', (string) $rating->id)->count())->toBe(1);
});
