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
    return User::factory()->unverified()->pendingStudentVerification()->create($attributes);
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
        ->set('first_name', 'Chileshe')
        ->set('last_name', 'Mwansa')
        ->set('email', 'chileshe@example.com')
        ->set('student_id', '2024198273')
        ->set('password', 'Sunshine123')
        ->set('password_confirmation', 'Sunshine123')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'chileshe@example.com')->firstOrFail();

    expect($user->is_verified)->toBeFalse()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->student_verification_status)->toBe(User::STUDENT_VERIFICATION_NOT_SUBMITTED);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('confirms email ownership but does not grant the Verified Student badge', function () {
    $user = unverifiedMember();

    $this->actingAs($user)->get(verificationUrl($user))
        ->assertRedirect(route('verification.student.form'))
        ->assertSessionHas('success', 'Email verified! Next, submit your student ID document so an administrator can verify your student account.');

    $fresh = $user->fresh();

    expect($fresh->hasVerifiedEmail())->toBeTrue()
        ->and($fresh->is_verified)->toBeFalse()
        ->and($fresh->student_verification_status)->toBe(User::STUDENT_VERIFICATION_NOT_SUBMITTED)
        ->and($fresh->canSubmitStudentVerification())->toBeTrue();
});

it('rejects tampered, unsigned or someone else\'s verification links', function () {
    $user = unverifiedMember();
    $other = unverifiedMember();

    $this->actingAs($user)->get(verificationUrl($user, sha1('wrong@example.com')))->assertForbidden();
    $this->actingAs($user)->get(verificationUrl($other))->assertForbidden();
    $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends guests who open a verification link to log in first', function () {
    $user = unverifiedMember();

    $this->get(verificationUrl($user))->assertRedirect(route('login'));
});

describe('the verification prompt', function () {
    it('nudges unverified members to confirm their email first', function () {
        $this->actingAs(unverifiedMember())->get(route('listings.index'))
            ->assertSee('Confirm your email address before you can request your Verified Student badge.');

        $this->actingAs(User::factory()->create())->get(route('listings.index'))
            ->assertDontSee('Confirm your email address');
    });

    it('nudges members with a confirmed email but no student document to submit one', function () {
        $user = User::factory()->pendingStudentVerification()->create();

        $this->actingAs($user)->get(route('listings.index'))
            ->assertSee('Submit your student ID document to earn the Verified Student badge.')
            ->assertDontSee('Confirm your email address');
    });

    it('tells members with a pending submission that it is under review, without nagging them to resubmit', function () {
        $user = User::factory()->studentVerificationSubmitted()->create();

        $this->actingAs($user)->get(route('listings.index'))
            ->assertSee('Your student verification document is under review.')
            ->assertDontSee('Submit your student ID document');
    });

    it('shows no verification banner to a fully verified student', function () {
        $this->actingAs(User::factory()->create())->get(route('listings.index'))
            ->assertDontSee('Confirm your email address')
            ->assertDontSee('Submit your student ID document')
            ->assertDontSee('under review');
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
