<?php

use App\Livewire\Auth\Login;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('sends a guest visiting / to the login form, showing no marketplace content', function () {
    $listing = Listing::factory()->create(['title' => 'A Secret Textbook Nobody Should See']);

    $this->get('/')->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in')
        ->assertDontSee('A Secret Textbook Nobody Should See')
        ->assertDontSee('Search textbooks, laptops, dorm gear');
});

it('redirects a guest away from every protected marketplace route', function (string $routeName, Closure $makeParams) {
    $this->get(route($routeName, $makeParams()))->assertRedirect(route('login'));
})->with([
    'home' => ['listings.index', fn () => []],
    'a listing' => ['listings.show', fn () => ['listing' => Listing::factory()->create()]],
    'a public profile' => ['profiles.show', fn () => ['user' => User::factory()->create()]],
]);

it('returns a guest to the exact page they originally wanted after logging in', function () {
    $listing = Listing::factory()->create();
    $user = User::factory()->create();

    $this->get(route('listings.show', $listing))->assertRedirect(route('login'));

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('listings.show', $listing));
});

it('lets a guest open login, register, forgot password, how-it-works and reputation verify', function () {
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();
    $this->get(route('password.request'))->assertOk();
    $this->get(route('how-it-works'))->assertOk();
    $this->get(route('reputation.verify'))->assertOk();
    $this->getJson('/api/v1/transparency/metrics')->assertOk();
});

it('shows the marketplace home to a signed-in user at /', function () {
    $listing = Listing::factory()->create(['title' => 'Visible Textbook']);

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('Visible Textbook');
});

it('shows no Sell button, search bar or bottom tab bar on the guest login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('aria-label="Sell item"', false)
        ->assertDontSee('Search textbooks, laptops, dorm gear')
        ->assertDontSee('aria-label="Primary navigation"', false);
});

it('shows the banner headline on guest pages and not on the marketplace home after login', function () {
    $this->get(route('login'))->assertSee('verified ZUT students');

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertDontSee('verified ZUT students');
});
