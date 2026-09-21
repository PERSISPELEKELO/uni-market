<?php

use App\Models\Listing;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => $this->withoutVite());

it('renders every public page without errors', function (string $routeName) {
    $this->get(route($routeName))->assertOk();
})->with(['home' => 'listings.index', 'login' => 'login', 'register' => 'register']);

it('renders every signed-in page without errors', function (string $routeName) {
    $this->actingAs(User::factory()->create())->get(route($routeName))->assertOk();
})->with([
    'sell an item' => 'listings.create',
    'my listings' => 'listings.mine',
    'messages' => 'chat.index',
    'my transactions' => 'transactions.tracker',
]);

it('ships an accessible, responsive page shell', function () {
    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false)
        ->assertSee('Skip to main content')
        ->assertSee('id="main-content"', false)
        ->assertSee('aria-label="Main navigation"', false);
});

it('shows account links to signed-in users and sign-in links to guests', function () {
    $this->get(route('listings.index'))
        ->assertSee('Log in')
        ->assertSee('Join UniMarket')
        ->assertDontSee('Log out');

    $this->actingAs(User::factory()->create(['name' => 'Chileshe Mwansa']))
        ->get(route('listings.index'))
        ->assertSee('Sell item')
        ->assertSee('My listings')
        ->assertSee('Log out')
        ->assertSee('Chileshe Mwansa')
        ->assertDontSee('Join UniMarket');
});

it('shows a friendly error page instead of a technical one for missing pages', function () {
    $this->get('/definitely-not-a-page')
        ->assertNotFound()
        ->assertSee('We could not find that page')
        ->assertSee('Back to the marketplace')
        ->assertDontSee('Stack trace');
});

it('shows a friendly forbidden page', function () {
    $listing = Listing::factory()->pending()->create();

    $this->actingAs($listing->seller)->get(route('listings.edit', $listing))
        ->assertForbidden()
        ->assertSee('You are not allowed to do that');
});

it('sends an expired session back to the login page with an explanation', function () {
    Route::middleware('web')->post('/_expired-form', fn () => throw new TokenMismatchException('CSRF token mismatch.'));

    $this->post('/_expired-form')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your session expired. Please log in again.');
});

it('ships a production stylesheet that contains the UniMarket colour system', function () {
    $manifestPath = public_path('build/manifest.json');

    if (! file_exists($manifestPath)) {
        $this->markTestSkipped('Run "npm run build" to generate the production assets first.');
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    $css = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

    expect($css)
        ->toContain('--color-brand-700')
        ->toContain('--color-accent-700')
        ->toContain('--color-danger-700')
        ->toContain('.btn-primary')
        ->toContain('.alert-error')
        ->toContain('.badge-success')
        ->toContain('.form-input');
});
