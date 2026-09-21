<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

describe('requesting a reset link', function () {
    it('is reachable from the login page', function () {
        $this->get(route('login'))->assertSee('Forgot password?')->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password?');
    });

    it('emails a reset link to a registered address', function () {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.com']);

        Livewire::test(ForgotPassword::class)
            ->set('email', '  Student@Example.com ')
            ->call('sendLink')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSee('If an account exists for that email address');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    });

    it('shows exactly the same result for an unknown address so accounts cannot be discovered', function () {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nobody@example.com')
            ->call('sendLink')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSee('If an account exists for that email address');

        Notification::assertNothingSent();
    });

    it('validates the email address', function () {
        Livewire::test(ForgotPassword::class)
            ->call('sendLink')
            ->assertHasErrors(['email' => 'required'])
            ->assertSee('Please enter your email address.')
            ->set('email', 'nope')
            ->call('sendLink')
            ->assertHasErrors(['email' => 'email'])
            ->assertSee('Please enter a valid email address.');
    });

    it('limits how many links can be requested', function () {
        Notification::fake();

        $component = Livewire::test(ForgotPassword::class)->set('email', 'nobody@example.com');

        foreach (range(1, 5) as $attempt) {
            $component->call('sendLink');
        }

        $component->set('sent', false)->call('sendLink')->assertHasErrors('email')->assertSee('Too many requests');
    });
});

describe('choosing a new password', function () {
    it('resets the password with a valid link and lets the member log in with it', function () {
        $user = User::factory()->create(['email' => 'student@example.com', 'password' => Hash::make('OldPassword1')]);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'student@example.com')
            ->set('password', 'BrandNew123')
            ->set('password_confirmation', 'BrandNew123')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        expect(Hash::check('BrandNew123', $user->fresh()->password))->toBeTrue()
            ->and(Hash::check('OldPassword1', $user->fresh()->password))->toBeFalse();

        Livewire::test(Login::class)
            ->set('email', 'student@example.com')
            ->set('password', 'BrandNew123')
            ->call('login')
            ->assertRedirect(route('listings.index'));
    });

    it('does not let the same link be used twice', function () {
        $user = User::factory()->create(['email' => 'student@example.com']);
        $token = Password::createToken($user);

        $attempt = fn (string $password) => Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'student@example.com')
            ->set('password', $password)
            ->set('password_confirmation', $password)
            ->call('resetPassword');

        $attempt('FirstChoice1')->assertRedirect(route('login'));
        $attempt('SecondChoice2')->assertHasErrors('email')->assertSee('invalid or has expired');

        expect(Hash::check('FirstChoice1', $user->fresh()->password))->toBeTrue();
    });

    it('rejects a wrong token or a different email', function () {
        $user = User::factory()->create(['email' => 'student@example.com', 'password' => Hash::make('OldPassword1')]);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => 'not-a-real-token'])
            ->set('email', 'student@example.com')
            ->set('password', 'BrandNew123')
            ->set('password_confirmation', 'BrandNew123')
            ->call('resetPassword')
            ->assertHasErrors('email');

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $other->email)
            ->set('password', 'BrandNew123')
            ->set('password_confirmation', 'BrandNew123')
            ->call('resetPassword')
            ->assertHasErrors('email');

        expect(Hash::check('OldPassword1', $user->fresh()->password))->toBeTrue();
    });

    it('enforces the same password rules as registration', function (string $password, string $confirmation, string $message) {
        $user = User::factory()->create(['email' => 'student@example.com']);

        Livewire::test(ResetPassword::class, ['token' => Password::createToken($user)])
            ->set('email', 'student@example.com')
            ->set('password', $password)
            ->set('password_confirmation', $confirmation)
            ->call('resetPassword')
            ->assertHasErrors('password')
            ->assertSee($message);
    })->with([
        'too short' => ['abc12', 'abc12', 'at least 8 characters'],
        'no number' => ['onlyletters', 'onlyletters', 'at least one number'],
        'mismatch' => ['Sunshine123', 'Sunshine124', 'do not match'],
    ]);

    it('shows the page with the email prefilled from the link', function () {
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'Student@Example.com']))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('student@example.com');
    });

    it('is not available to signed-in members', function () {
        $this->actingAs(User::factory()->create())->get(route('password.request'))->assertRedirect();
    });
});
