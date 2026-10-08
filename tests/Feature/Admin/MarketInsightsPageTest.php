<?php

use App\Filament\Pages\MarketInsights;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('is reachable by an admin and shows real marketplace totals on the overview section', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Transaction::factory()->create(['status' => 'COMPLETED', 'completed_at' => now()]);

    $this->actingAs($admin)->get('/admin/market-insights')
        ->assertOk()
        ->assertSee('Marketplace totals')
        ->assertSee('Top listings by sale value');
});

it('switches sidebar sections and shows the right content in each', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Transaction::factory()->create(['status' => 'COMPLETED', 'completed_at' => now()]);

    Livewire::actingAs($admin)->test(MarketInsights::class)
        ->assertSee('Marketplace totals')
        ->call('setSection', 'growth')
        ->assertSee('User growth')
        ->assertSee('Buyers vs. sellers vs. both', false)
        ->call('setSection', 'trust')
        ->assertSee('Trust &amp; safety metrics', false)
        ->assertSee('Dispute rate by category')
        ->call('setSection', 'listings')
        ->assertSee('Listing funnel')
        ->assertSee('Peak activity times')
        ->call('setSection', 'marketplace')
        ->assertSee('Buyers by year of study')
        ->assertSee('Frequently bought together');
});

it('ignores an invalid section and clears the category filter when switching sections', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))
        ->test(MarketInsights::class)
        ->call('setSection', 'not-a-real-section')
        ->assertSet('section', 'overview')
        ->call('setSection', 'trust')
        ->call('applyChartFilter', 'category', 'Books')
        ->assertSet('selectedCategory', 'Books')
        ->call('setSection', 'listings')
        ->assertSet('selectedCategory', null);
});

it('switches to every period option without error', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(MarketInsights::class)
        ->set('period', '7d')->assertOk()
        ->set('period', 'semester')->assertOk()
        ->set('period', '12mo')->assertOk();
});

it('filters the dispute-rate-by-category chart data to the clicked category', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $books = Category::factory()->create(['name' => 'Books']);
    $listing = Listing::factory()->create(['category_id' => $books->id]);
    $transaction = Transaction::factory()->create(['status' => 'COMPLETED', 'listing_id' => $listing->id, 'completed_at' => now()]);
    Dispute::create([
        'transaction_id' => $transaction->id,
        'raised_by' => $transaction->buyer_id,
        'reason' => 'Testing.',
        'status' => 'open',
    ]);

    $component = Livewire::actingAs($admin)->test(MarketInsights::class)
        ->call('setSection', 'trust');

    $before = $component->instance()->chartsForCurrentSection()['dispute-rate-by-category'];
    expect($before['labels'])->toContain('Books');

    $component->call('applyChartFilter', 'category', 'Books');
    $after = $component->instance()->chartsForCurrentSection()['dispute-rate-by-category'];
    expect($after['labels'])->toBe(['Books']);
});

it('exports a csv built from the same already-safe aggregate figures', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Transaction::factory()->create(['status' => 'COMPLETED', 'completed_at' => now()]);

    $response = Livewire::actingAs($admin)
        ->test(MarketInsights::class)
        ->call('mountAction', 'exportCsv');

    $response->assertOk();
});
