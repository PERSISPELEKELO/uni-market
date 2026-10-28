<?php

use App\Livewire\Transactions\TransactionsHub;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionInboxService;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('shows a seller a pending handover under "Needs your action", and the badge count survives logging out and back in', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transaction = Transaction::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'status' => 'PENDING_MEETING']);

    expect(app(TransactionInboxService::class)->needsActionCount($seller))->toBe(1);

    Livewire::actingAs($seller)->test(TransactionsHub::class)
        ->assertSee('Enter handover code')
        ->assertSee($transaction->listing->title);

    auth()->logout();
    $this->actingAs($seller);

    expect(app(TransactionInboxService::class)->needsActionCount($seller))->toBe(1);
});

it('shows the buyer the same transaction as in progress while waiting to meet, then needing action once handed over', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transaction = Transaction::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'status' => 'PENDING_MEETING']);

    Livewire::actingAs($buyer)->test(TransactionsHub::class)
        ->set('activeTab', 'in_progress')
        ->assertSee('Waiting to meet the seller');

    $transaction->update(['status' => 'ITEM_INSPECTION']);

    Livewire::actingAs($buyer)->test(TransactionsHub::class)
        ->assertSee('Confirm item or raise a dispute');
});

it('groups completed, disputed and refunded transactions into their own tabs', function () {
    $user = User::factory()->create();
    Transaction::factory()->create(['buyer_id' => $user->id, 'status' => 'COMPLETED', 'completed_at' => now()]);
    Transaction::factory()->create(['buyer_id' => $user->id, 'status' => 'DISPUTED']);
    Transaction::factory()->create(['buyer_id' => $user->id, 'status' => 'REFUNDED']);

    $grouped = app(TransactionInboxService::class)->grouped($user);

    // The completed one still needs the buyer to rate - it belongs in "needs action", not "completed".
    expect($grouped['needs_action'])->toHaveCount(1)
        ->and($grouped['disputed'])->toHaveCount(2)
        ->and($grouped['completed'])->toHaveCount(0);
});

it('moves a completed transaction to "Completed" once the user has rated it', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $transaction = Transaction::factory()->create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'COMPLETED', 'completed_at' => now()]);

    Rating::create(['transaction_id' => $transaction->id, 'rater_id' => $buyer->id, 'rated_id' => $seller->id, 'stars' => 5]);

    $grouped = app(TransactionInboxService::class)->grouped($buyer);

    expect($grouped['completed'])->toHaveCount(1)
        ->and($grouped['needs_action'])->toHaveCount(0);
});

it('updates the badge count after the seller enters the handover code', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $transaction = Transaction::factory()->create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'status' => 'PENDING_MEETING']);

    expect(app(TransactionInboxService::class)->needsActionCount($seller))->toBe(1)
        ->and(app(TransactionInboxService::class)->needsActionCount($buyer))->toBe(0);

    $transaction->update(['status' => 'ITEM_INSPECTION']);

    expect(app(TransactionInboxService::class)->needsActionCount($seller))->toBe(0)
        ->and(app(TransactionInboxService::class)->needsActionCount($buyer))->toBe(1);
});

it('never shows another user\'s transaction, and hides it (404) if they open it directly', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $transaction = Transaction::factory()->create(['buyer_id' => $owner->id]);

    Livewire::actingAs($stranger)->test(TransactionsHub::class)
        ->assertDontSee($transaction->listing->title);

    // TransactionPolicy::view() deliberately denies as "not found" rather
    // than 403, so a stranger can't tell the transaction exists at all.
    $this->actingAs($stranger)->get(route('transactions.tracker', $transaction))->assertNotFound();
});

it('still serves the old tracker URL directly', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create(['buyer_id' => $user->id]);

    $this->actingAs($user)->get(route('transactions.tracker', $transaction))->assertOk();
});

it('never puts the buyer\'s handover code on the seller\'s hub page', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    Transaction::factory()->create([
        'seller_id' => $seller->id,
        'buyer_id' => $buyer->id,
        'status' => 'PENDING_MEETING',
        'handover_otp_plain' => '123456',
    ]);

    $this->actingAs($seller)->get(route('transactions.index'))->assertDontSee('123456');
});
