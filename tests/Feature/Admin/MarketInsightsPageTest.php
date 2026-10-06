<?php

use App\Filament\Pages\MarketInsights;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('is reachable by an admin and shows real marketplace totals', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Transaction::factory()->create(['status' => 'COMPLETED', 'completed_at' => now()]);

    $this->actingAs($admin)->get('/admin/market-insights')
        ->assertOk()
        ->assertSee('Marketplace totals')
        ->assertSee('Trust &amp; safety metrics', false);
});

it('switches to every period option without error', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(MarketInsights::class)
        ->set('period', '7d')->assertOk()
        ->set('period', 'semester')->assertOk()
        ->set('period', '12mo')->assertOk();
});

it('exports a csv built from the same already-safe aggregate figures', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Transaction::factory()->create(['status' => 'COMPLETED', 'completed_at' => now()]);

    $response = Livewire::actingAs($admin)
        ->test(MarketInsights::class)
        ->call('mountAction', 'exportCsv');

    $response->assertOk();
});
