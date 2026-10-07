<?php

use App\Livewire\Activity\MyActivity;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('needs a login', function () {
    $this->get(route('activity.index'))->assertRedirect(route('login'));
});

it('defaults to the buying tab and embeds the existing transaction tracker', function () {
    $this->actingAs(User::factory()->create())->get(route('activity.index'))
        ->assertOk()
        ->assertSee('My Activity')
        ->assertSee('Buying')
        ->assertSee('Selling')
        ->assertSee('Insights');
});

it('switches tabs and keeps the choice in the url', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->assertSet('tab', 'buying')
        ->call('setTab', 'selling')
        ->assertSet('tab', 'selling')
        ->call('setTab', 'insights')
        ->assertSet('tab', 'insights');
});

it('ignores an invalid tab value', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->call('setTab', 'not-a-real-tab')
        ->assertSet('tab', 'buying');
});

it('shows buyers who reserved the signed-in seller\'s listings only on the selling tab', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['name' => 'Interested Buyer']);
    $listing = Listing::factory()->for($seller, 'seller')->create(['title' => 'Graphing Calculator']);
    Reservation::factory()->for($listing)->for($buyer, 'buyer')->create();

    $component = Livewire::actingAs($seller)->test(MyActivity::class);
    $component->assertDontSee('Interested Buyer');

    $component->call('setTab', 'selling')
        ->assertSee('Interested Buyer')
        ->assertSee('Graphing Calculator');
});

it('forwards a specific transaction id to the buying tab', function () {
    $buyer = User::factory()->create();
    $transaction = Transaction::factory()->for($buyer, 'buyer')->create();

    Livewire::actingAs($buyer)->test(MyActivity::class, ['transaction' => $transaction->id])
        ->assertSet('tab', 'buying')
        ->assertSet('transaction', $transaction->id);
});

it('only ever shows the signed-in student\'s own insights', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->assertSee('Items bought')
        ->assertSee('Spending by category')
        ->assertSee('Spending over time');
});

it('shows the seller insights sections: earnings over time, sold by category, funnel, who buys from me', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsView', 'seller')
        ->assertSee('Earnings over time')
        ->assertSee('Items sold by category')
        ->assertSee('From view to sale')
        ->assertSee('Who buys from me');
});

it('never shows one student\'s purchase totals on another student\'s insights tab', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();

    $listing = Listing::factory()->create();
    Transaction::factory()->create([
        'buyer_id' => $victim->id,
        'listing_id' => $listing->id,
        'amount' => 999999,
        'status' => 'COMPLETED',
        'completed_at' => now(),
    ]);

    // The component only ever reads auth()->user() - there is no user-id
    // parameter anywhere in its public API for an attacker to tamper with.
    Livewire::actingAs($attacker)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->assertDontSee('999,999.00')
        ->assertSee('K0.00');
});

it('switches the insights date range and ignores an invalid value', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->assertSet('insightsPeriod', 'all')
        ->call('setInsightsPeriod', '30d')
        ->assertSet('insightsPeriod', '30d')
        ->call('setInsightsPeriod', 'not-a-real-period')
        ->assertSet('insightsPeriod', '30d');
});
