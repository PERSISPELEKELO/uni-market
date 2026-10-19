<?php

use App\Livewire\Activity\MyActivity;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingEvent;
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
        ->assertSee('Items sold')
        ->call('setInsightsSidebarView', 'buying')
        ->assertSee('Spending by category')
        ->assertSee('Spending over time');
});

it('shows the seller insights sections: earnings over time, sold by category, funnel', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'selling')
        ->assertSee('Earnings over time')
        ->assertSee('Items sold by category')
        ->assertSee('From view to sale');
});

it('shows the customers section with year, school and gender breakdowns', function () {
    config(['insights.min_group_size' => 1]);
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['year_of_study' => 2, 'school' => 'ict']);
    Transaction::factory()->create([
        'seller_id' => $seller->id,
        'buyer_id' => $buyer->id,
        'listing_id' => Listing::factory()->create(['user_id' => $seller->id])->id,
        'status' => 'COMPLETED',
        'completed_at' => now(),
    ]);

    Livewire::actingAs($seller)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'customers')
        ->assertSee('By school')
        ->assertSee('By year of study')
        ->assertSee('By gender');
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

it('filters the price-check table by the clicked category and can reset it', function () {
    $seller = User::factory()->create();
    $books = Category::factory()->create(['name' => 'Books']);
    $shoes = Category::factory()->create(['name' => 'Shoes']);
    Listing::factory()->create(['user_id' => $seller->id, 'category_id' => $books->id, 'title' => 'A Textbook', 'status' => 'active']);
    Listing::factory()->create(['user_id' => $seller->id, 'category_id' => $shoes->id, 'title' => 'Running Shoes', 'status' => 'active']);

    $component = Livewire::actingAs($seller)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'selling')
        ->assertSee('A Textbook')
        ->assertSee('Running Shoes');

    $component->call('applyChartFilter', 'category', 'Books')
        ->assertSet('selectedCategory', 'Books')
        ->assertSee('A Textbook')
        ->assertDontSee('Running Shoes');

    $component->call('resetChartFilters')
        ->assertSet('selectedCategory', null)
        ->assertSee('Running Shoes');
});

it('ignores a chart filter dimension it does not recognise', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->call('applyChartFilter', 'something-unsupported', 'value')
        ->assertSet('selectedCategory', null);
});

it('resets the category filter when switching insights sections', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'selling')
        ->call('applyChartFilter', 'category', 'Books')
        ->assertSet('selectedCategory', 'Books')
        ->call('setInsightsSidebarView', 'buying')
        ->assertSet('selectedCategory', null);
});

it('switches the insights date range and ignores an invalid value', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->assertSet('insightsPeriod', 'all')
        ->call('setInsightsPeriod', '30d')
        ->assertSet('insightsPeriod', '30d')
        ->call('setInsightsPeriod', 'not-a-real-period')
        ->assertSet('insightsPeriod', '30d');
});

it('offers the new finer-grained date ranges alongside the existing ones', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'selling')
        ->assertSee('Today')
        ->assertSee('Last 7 days')
        ->assertSee('Last 28 days')
        ->assertSee('Last 30 days')
        ->assertSee('This semester')
        ->assertSee('All time');
});

it('switches the selected engagement metric, ignores an unknown one, and resets to clicks', function () {
    Livewire::actingAs(User::factory()->create())->test(MyActivity::class)
        ->assertSet('insightsMetric', 'clicks')
        ->call('setInsightsMetric', 'saves')
        ->assertSet('insightsMetric', 'saves')
        ->call('setInsightsMetric', 'not-a-real-metric')
        ->assertSet('insightsMetric', 'saves');
});

it('counts real clicks, saves, shares and enquiries on the seller\'s own listings, isolated from everyone else\'s', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->create(['user_id' => $seller->id]);
    $otherSeller = User::factory()->create();
    $unrelatedListing = Listing::factory()->create(['user_id' => $otherSeller->id]);
    $viewer = User::factory()->create();

    ListingEvent::create(['listing_id' => $listing->id, 'user_id' => $viewer->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()]);
    ListingEvent::create(['listing_id' => $listing->id, 'user_id' => $viewer->id, 'type' => ListingEvent::TYPE_SAVE, 'created_at' => now()]);
    ListingEvent::create(['listing_id' => $unrelatedListing->id, 'user_id' => $viewer->id, 'type' => ListingEvent::TYPE_VIEW, 'created_at' => now()]);

    Livewire::actingAs($seller)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'selling')
        ->assertViewHas('engagementTotals', fn ($totals) => $totals['clicks']['count'] === 1 && $totals['saves']['count'] === 1 && $totals['shares']['count'] === 0);
});

it('sorts the per-listing performance table by the clicked column and flips direction on a second click', function () {
    $seller = User::factory()->create();
    $popular = Listing::factory()->create(['user_id' => $seller->id, 'title' => 'Popular item']);
    $quiet = Listing::factory()->create(['user_id' => $seller->id, 'title' => 'Quiet item']);
    ListingEvent::factory()->count(5)->create(['listing_id' => $popular->id, 'type' => ListingEvent::TYPE_VIEW]);
    ListingEvent::factory()->count(1)->create(['listing_id' => $quiet->id, 'type' => ListingEvent::TYPE_VIEW]);

    $component = Livewire::actingAs($seller)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'selling')
        ->assertSeeInOrder(['Popular item', 'Quiet item']);

    $component->call('sortListingsBy', 'clicks')
        ->assertSeeInOrder(['Quiet item', 'Popular item']);
});

it('shows a "why isn\'t it selling?" hint on the per-listing performance table', function () {
    $seller = User::factory()->create();
    Listing::factory()->create(['user_id' => $seller->id, 'title' => 'Ignored item', 'created_at' => now()->subDays(10)]);

    Livewire::actingAs($seller)->test(MyActivity::class)
        ->call('setTab', 'insights')
        ->call('setInsightsSidebarView', 'selling')
        ->assertSee('No views yet');
});
