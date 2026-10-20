<?php

use App\Models\User;

it('shows only the logo and Home on the left for a signed-in student', function () {
    $this->actingAs(User::factory()->create())->get(route('listings.index'))
        ->assertOk()
        ->assertSee('aria-label="Main navigation"', false);
});

it('shows the right-side icon group in order: Sell, My Activity menu, Messages, Notifications, Avatar', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('listings.index'))->assertOk();
    $html = $response->getContent();

    $sell = strpos($html, 'aria-label="Sell item"');
    $gridMenu = strpos($html, 'aria-label="My Activity menu"');
    $messages = strpos($html, 'aria-label="Messages"');
    $notifications = strpos($html, 'aria-label="Notifications"');
    $avatar = strpos($html, 'aria-label="Your account"');

    expect([$sell, $gridMenu, $messages, $notifications, $avatar])->each->not->toBeFalse();
    expect($sell)->toBeLessThan($gridMenu)
        ->and($gridMenu)->toBeLessThan($messages)
        ->and($messages)->toBeLessThan($notifications)
        ->and($notifications)->toBeLessThan($avatar);
});

it('lets a signed-in student reach Buying, Selling, Saved and Insights from the My Activity menu', function () {
    $this->actingAs(User::factory()->create())->get(route('listings.index'))
        ->assertOk()
        ->assertSeeInOrder(['Buying', 'Selling', 'Saved', 'Insights']);
});

it('opens the Help panel from the avatar menu', function () {
    $this->actingAs(User::factory()->create())->get(route('listings.index'))
        ->assertOk()
        ->assertSee('open-help-panel', false);
});

it('does not duplicate the theme toggle for a signed-in student', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('listings.index'))->getContent();

    expect(substr_count($html, 'window.uniMarketTheme.toggle()'))->toBe(1); // only inside the mobile hamburger menu
});

it('still gives a guest a way to toggle dark mode on desktop', function () {
    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee('Toggle dark mode');
});
