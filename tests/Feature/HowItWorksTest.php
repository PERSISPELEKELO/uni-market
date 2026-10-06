<?php

use App\Models\User;

beforeEach(fn () => $this->withoutVite());

it('is publicly readable without an account', function () {
    $this->get(route('how-it-works'))
        ->assertOk()
        ->assertSee('How UniMarket works')
        ->assertSee('Direct purchase')
        ->assertSee('Inspection-protected purchase');
});

it('is linked from the footer', function () {
    $this->get(route('listings.index'))->assertSee(route('how-it-works'), false);
});

it('is reachable for signed-in students too', function () {
    $this->actingAs(User::factory()->create())->get(route('how-it-works'))->assertOk();
});
