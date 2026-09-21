<?php

use App\Livewire\Auth\Register;
use App\Livewire\Auth\VerifyEmail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

function unverifiedMember(array $attributes = []): User
{
    return User::factory()->unverified()->create(array_merge(['is_verified' => false], $attributes));
}

function verificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->getEmailForVerification()),
    ]);
}

it('registers members without the verified badge and emails them a verification link', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Chileshe Mwansa')
        ->set('email', 'chileshe@example.com')
        ->set('student_id', '2024198273')
        ->set('password', 'Sunshine123')
        ->set('password_confirmation', 'Sunshine123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'chileshe@example.com')->firstOrFail();

    expect($user->is_verified)->toBeFalse()
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('grants the Official Student badge when the emailed link is opened', function () {
    $user = unverifiedMember();

    $this->actingAs($user)->get(verificationUrl($user))
        ->assertRedirect(route('listings.index'))
        ->assertSessionHas('success', 'Email verified! Your Official Student badge is now active.');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->is_verified)->toBeTrue();
});

it('rejects tampered, unsigned or someone else\'s verification links', function () {
    $user = unverifiedMember();
    $other = unverifiedMember();

    $this->actingAs($user)->get(verificationUrl($user, sha1('wrong@example.com')))->assertForbidden();
    $this->actingAs($user)->get(verificationUrl($other))->assertForbidden();
    $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();

    expect($user->fresh()->is_verified)->toBeFalse()->and($other->fresh()->is_verified)->toBeFalse();
});

it('sends guests who open a verification link to log in first', function () {
    $user = unverifiedMember();

    $this->get(verificationUrl($user))->assertRedirect(route('login'));
});

describe('university email domains', function () {
    it('gives a verified email the badge only when its domain is allowed', function (string $email, bool $expectsBadge) {
        config(['unimarket.student_email_domains' => ['student.zut.zm']]);
        $user = unverifiedMember(['email' => $email]);

        $this->actingAs($user)->get(verificationUrl($user))->assertRedirect();

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
            ->and($user->fresh()->is_verified)->toBe($expectsBadge);
    })->with([
        'exact domain' => ['chileshe@student.zut.zm', true],
        'subdomain' => ['chileshe@mail.student.zut.zm', true],
        'uppercase' => ['Chileshe@STUDENT.ZUT.ZM', true],
        'other provider' => ['chileshe@gmail.com', false],
        'lookalike suffix' => ['chileshe@evilstudent.zut.zm', false],
        'domain inside another' => ['chileshe@student.zut.zm.attacker.com', false],
    ]);

    it('accepts any provider when no domains are configured', function () {
        config(['unimarket.student_email_domains' => []]);
        $user = unverifiedMember(['email' => 'anyone@gmail.com']);

        $this->actingAs($user)->get(verificationUrl($user));

        expect($user->fresh()->is_verified)->toBeTrue();
    });
});

describe('the verification prompt', function () {
    it('nudges unverified members, but not verified ones', function () {
        $this->actingAs(unverifiedMember())->get(route('listings.index'))
            ->assertSee('Confirm your email address to earn the Official Student badge.');

        $this->actingAs(User::factory()->create())->get(route('listings.index'))
            ->assertDontSee('Confirm your email address');
    });

    it('does not nag members who were verified before email confirmation existed', function () {
        $legacy = User::factory()->unverified()->create(['is_verified' => true]);

        $this->actingAs($legacy)->get(route('listings.index'))->assertDontSee('Confirm your email address');
    });

    it('lets a member resend the link, with a limit', function () {
        Notification::fake();
        $user = unverifiedMember();

        $component = Livewire::actingAs($user)->test(VerifyEmail::class);

        foreach (range(1, 3) as $attempt) {
            $component->call('resend')->assertHasNoErrors();
        }

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);

        $component->call('resend')->assertHasErrors('resend')->assertSee('Please wait');

        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
    });

    it('renders for members and needs a login', function () {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));

        $this->actingAs(unverifiedMember(['email' => 'pending@example.com']))->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('pending@example.com')
            ->assertSee('Send me a new link');
    });
});
