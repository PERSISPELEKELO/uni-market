<?php

use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;

beforeEach(fn () => $this->withoutVite());

it('shows the seller\'s profile card, rating and verified badge to the buyer on the hub and tracker', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create(['name' => 'Verified Seller', 'is_verified' => true]);
    $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'HANDED_OVER']);

    $rater = User::factory()->create();
    $ratedTransaction = Transaction::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $rater->id, 'status' => 'COMPLETED']);
    Rating::create(['transaction_id' => $ratedTransaction->id, 'rater_id' => $rater->id, 'rated_id' => $seller->id, 'stars' => 5]);

    $this->actingAs($buyer)->get(route('transactions.index'))
        ->assertSee('Verified Seller')
        ->assertSee('Verified')
        ->assertSee('5.0')
        ->assertSee(route('profiles.show', $seller), false);

    $this->actingAs($buyer)->get(route('transactions.tracker', $transaction))
        ->assertSee('Verified Seller')
        ->assertSee(route('profiles.show', $seller), false);
});

it('shows the buyer\'s profile card to the seller, labelled "Buyer"', function () {
    $buyer = User::factory()->create(['name' => 'Interested Buyer']);
    $seller = User::factory()->create();
    $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'PENDING_MEETING']);

    $this->actingAs($seller)->get(route('transactions.tracker', $transaction))
        ->assertSee('Interested Buyer')
        ->assertSee('Buyer');
});

it('links the Message button on a counterparty card to a chat with that person about this transaction', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'PENDING_MEETING']);

    $this->actingAs($buyer)->get(route('transactions.tracker', $transaction))
        ->assertSee(route('chat.thread', ['receiver' => $seller->id, 'listing' => $transaction->listing_id, 'transaction' => $transaction->id]), false);
});
