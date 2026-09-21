<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('shows the login page to guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Email address')
        ->assertSee('Password');
});

it('redirects logged-in users away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect();
});

it('rejects an empty email and password with friendly messages', function () {
    Livewire::test(Login::class)
        ->call('login')
        ->assertHasErrors(['email' => 'required', 'password' => 'required'])
        ->assertSee('Please enter your email address.')
        ->assertSee('Please enter your password.');

    $this->assertGuest();
});

it('rejects an invalid email format', function () {
    Livewire::test(Login::class)
        ->set('email', 'not-an-email')
        ->set('password', 'secret123')
        ->call('login')
        ->assertHasErrors(['email' => 'email'])
        ->assertSee('Please enter a valid email address.');

    $this->assertGuest();
});

it('does not log in an email that has no account', function () {
    Livewire::test(Login::class)
        ->set('email', 'nobody@example.com')
        ->set('password', 'Password123')
        ->call('login')
        ->assertHasErrors('credentials')
        ->assertSee('The email or password you entered is incorrect.');

    $this->assertGuest();
});

it('does not log in with a wrong password', function () {
    $user = User::factory()->create(['password' => Hash::make('CorrectPass1')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'WrongPass1')
        ->call('login')
        ->assertHasErrors('credentials')
        ->assertSee('The email or password you entered is incorrect.')
        ->assertSet('password', '');

    $this->assertGuest();
});

it('gives the same error for an unknown email and a wrong password so accounts cannot be enumerated', function () {
    $user = User::factory()->create(['password' => Hash::make('CorrectPass1')]);

    $unknown = Livewire::test(Login::class)
        ->set('email', 'ghost@example.com')
        ->set('password', 'CorrectPass1')
        ->call('login')
        ->errors()->get('credentials');

    $wrongPassword = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'nope-nope-1')
        ->call('login')
        ->errors()->get('credentials');

    expect($unknown)->toBe($wrongPassword);
});

it('logs in with valid credentials, ignoring email case and surrounding spaces', function () {
    $user = User::factory()->create([
        'email' => 'student@example.com',
        'password' => Hash::make('CorrectPass1'),
    ]);

    Livewire::test(Login::class)
        ->set('email', '  Student@Example.COM ')
        ->set('password', 'CorrectPass1')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('listings.index'));

    $this->assertAuthenticatedAs($user);
});

it('locks out repeated failed attempts, even when the correct password is then used', function () {
    $user = User::factory()->create(['password' => Hash::make('CorrectPass1')]);

    $component = Livewire::test(Login::class)->set('email', $user->email);

    foreach (range(1, 5) as $attempt) {
        $component->set('password', 'WrongPass'.$attempt)->call('login');
    }

    $component
        ->set('password', 'CorrectPass1')
        ->call('login')
        ->assertHasErrors('credentials')
        ->assertSee('Too many login attempts');

    $this->assertGuest();
});

it('logs a user out and invalidates the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('listings.index'));

    $this->assertGuest();
});

it('keeps guests out of pages that need an account', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'sell an item' => 'listings.create',
    'my listings' => 'listings.mine',
    'messages' => 'chat.index',
    'my transactions' => 'transactions.tracker',
]);
