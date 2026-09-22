<?php

use App\Livewire\Ratings\RateTransaction;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

function completedTx(array $overrides = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'buyer_id' => User::factory(),
        'seller_id' => User::factory(),
        'status' => 'COMPLETED',
    ], $overrides));
}

it('renders nothing for a transaction that is not yet completed', function () {
    $transaction = completedTx(['status' => 'PENDING_MEETING']);

    Livewire::actingAs($transaction->buyer)->test(RateTransaction::class, ['transaction' => $transaction])
        ->assertDontSee('Rate your experience')
        ->assertDontSee('Submit rating');
});

it('renders nothing for someone who was not part of the transaction', function () {
    $transaction = completedTx();

    Livewire::actingAs(User::factory()->create())->test(RateTransaction::class, ['transaction' => $transaction])
        ->assertDontSee('Rate your experience')
        ->assertDontSee('Submit rating');
});

it('shows the rating form to an eligible participant and submits it correctly', function () {
    $transaction = completedTx();

    Livewire::actingAs($transaction->buyer)->test(RateTransaction::class, ['transaction' => $transaction])
        ->assertSee('Rate your experience with '.$transaction->seller->name)
        ->call('submit')
        ->assertHasErrors('stars')
        ->set('stars', 5)
        ->set('comment', 'Smooth transaction, item as described.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('notify', type: 'success');

    $rating = Rating::firstOrFail();
    expect($rating->rater_id)->toBe($transaction->buyer_id)
        ->and($rating->rated_id)->toBe($transaction->seller_id)
        ->and($rating->stars)->toBe(5)
        ->and($rating->comment)->toBe('Smooth transaction, item as described.');
});

it('shows a recap instead of the form once rated, and cannot be submitted twice', function () {
    $transaction = completedTx();

    $component = Livewire::actingAs($transaction->buyer)->test(RateTransaction::class, ['transaction' => $transaction]);
    $component->set('stars', 4)->call('submit');

    $component->assertSee('You rated '.$transaction->seller->name.' 4 out of 5')
        ->assertDontSee('Submit rating');

    // Even a direct re-call cannot create a second row (defence in depth beyond hiding the form).
    $component->set('stars', 1)->call('submit');
    expect(Rating::where('transaction_id', $transaction->id)->where('rater_id', $transaction->buyer_id)->count())->toBe(1);
});

it('rejects an out-of-range star value even if the client sends one', function () {
    $transaction = completedTx();

    Livewire::actingAs($transaction->buyer)->test(RateTransaction::class, ['transaction' => $transaction])
        ->set('stars', 9)
        ->call('submit')
        ->assertHasErrors('stars');

    expect(Rating::count())->toBe(0);
});

it('does not let a rater\'s crafted request rate a transaction outsider is not part of', function () {
    // Even calling the component action directly (bypassing the UI entirely), the service
    // still refuses because rated_id/eligibility are derived server-side from the transaction.
    $transaction = completedTx();
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)->test(RateTransaction::class, ['transaction' => $transaction])
        ->set('stars', 5)
        ->call('submit')
        ->assertDispatched('notify', type: 'error');

    expect(Rating::count())->toBe(0);
});
