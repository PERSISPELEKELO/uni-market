<?php

use App\Livewire\Notifications\Bell;
use App\Models\User;
use App\Notifications\StudentVerificationApproved;
use App\Notifications\StudentVerificationRejected;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('is not shown to guests', function () {
    $this->get(route('listings.index'))->assertDontSee('wire:poll.20s', false);
});

it('is shown to a signed-in member', function () {
    $this->actingAs(User::factory()->create())->get(route('listings.index'))
        ->assertSee('wire:poll.20s', false);
});

it('shows the unread count and the notification list to a signed-in member', function () {
    $user = User::factory()->create();
    $user->notify(new StudentVerificationApproved);
    $user->notify(new StudentVerificationRejected(false, 'The photo was blurry.'));

    Livewire::actingAs($user)->test(Bell::class)
        ->assertSee('You are a Verified Student')
        ->assertSee('Your student verification was not approved')
        ->assertSee('2'); // the unread badge

    expect($user->unreadNotifications()->count())->toBe(2);
});

it('never shows another member\'s notifications', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $other->notify(new StudentVerificationApproved);

    Livewire::actingAs($me)->test(Bell::class)->assertDontSee('You are a Verified Student');

    expect($me->unreadNotifications()->count())->toBe(0);
});

it('marks a notification read and sends the member to what it is about', function () {
    $user = User::factory()->create();
    $user->notify(new StudentVerificationApproved);
    $notification = $user->notifications()->firstOrFail();

    Livewire::actingAs($user)->test(Bell::class)
        ->call('open', $notification->id)
        ->assertRedirect(route('account'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('does not let a member mark someone else\'s notification as read', function () {
    $other = User::factory()->create();
    $other->notify(new StudentVerificationApproved);
    $notification = $other->notifications()->firstOrFail();

    Livewire::actingAs(User::factory()->create())->test(Bell::class)->call('open', $notification->id);

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks every notification read at once', function () {
    $user = User::factory()->create();
    $user->notify(new StudentVerificationApproved);
    $user->notify(new StudentVerificationRejected(true, null));

    Livewire::actingAs($user)->test(Bell::class)->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

describe('when the session has expired while the bell is still mounted', function () {
    it('renders (and re-renders, as a poll would) without a signed-in user instead of crashing', function () {
        Livewire::test(Bell::class)
            ->assertSuccessful()
            ->assertSee('You have no notifications yet.')
            ->call('$refresh')
            ->assertSuccessful();
    });

    it('does not crash when markAllAsRead runs with no signed-in user', function () {
        Livewire::test(Bell::class)->call('markAllAsRead')->assertSuccessful();
    });

    it('does not crash when open runs with no signed-in user', function () {
        Livewire::test(Bell::class)->call('open', 'some-id')->assertSuccessful();
    });
});
