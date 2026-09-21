<?php

use App\Livewire\Chat\MessageThread;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('does not let a user open a chat with themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('chat.thread', ['receiver' => $user->id]))
        ->assertRedirect(route('chat.index'))
        ->assertSessionHas('warning');
});

it('returns 404 when the other person does not exist', function () {
    $this->actingAs(User::factory()->create())->get(route('chat.thread', ['receiver' => 99999]))->assertNotFound();
});

it('sends a message about a listing', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create();

    Livewire::actingAs($buyer)->test(MessageThread::class, ['receiver' => $listing->user_id, 'listing' => $listing->id])
        ->set('newMessage', '  Is this still available?  ')
        ->call('sendMessage')
        ->assertHasNoErrors()
        ->assertSet('newMessage', '')
        ->assertSee('Is this still available?');

    $message = Message::firstOrFail();

    expect($message->sender_id)->toBe($buyer->id)
        ->and($message->receiver_id)->toBe($listing->user_id)
        ->and($message->listing_id)->toBe($listing->id)
        ->and($message->message)->toBe('Is this still available?');
});

it('rejects empty and over-long messages', function () {
    $other = User::factory()->create();

    Livewire::actingAs(User::factory()->create())->test(MessageThread::class, ['receiver' => $other->id])
        ->set('newMessage', '   ')
        ->call('sendMessage')
        ->assertHasErrors(['newMessage' => 'required'])
        ->assertSee('Type a message before sending.')
        ->set('newMessage', str_repeat('a', 1001))
        ->call('sendMessage')
        ->assertHasErrors(['newMessage' => 'max']);

    expect(Message::count())->toBe(0);
});

it('escapes message content instead of rendering html', function () {
    $other = User::factory()->create();

    Livewire::actingAs(User::factory()->create())->test(MessageThread::class, ['receiver' => $other->id])
        ->set('newMessage', '<img src=x onerror=alert(1)>')
        ->call('sendMessage')
        ->assertDontSeeHtml('<img src=x onerror=alert(1)>')
        ->assertSeeHtml('&lt;img src=x onerror=alert(1)&gt;');
});

it('never shows other people\'s conversations', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create(['name' => 'Friendly Fran']);
    $strangerA = User::factory()->create(['name' => 'Stranger Alpha']);
    $strangerB = User::factory()->create(['name' => 'Stranger Bravo']);

    Message::factory()->create(['sender_id' => $me->id, 'receiver_id' => $friend->id, 'message' => 'Hi Fran, see you at the library']);
    Message::factory()->create(['sender_id' => $strangerA->id, 'receiver_id' => $strangerB->id, 'message' => 'Private plans between strangers']);

    $this->actingAs($me)->get(route('chat.index'))
        ->assertOk()
        ->assertSee('Friendly Fran')
        ->assertSee('Hi Fran, see you at the library')
        ->assertDontSee('Private plans between strangers')
        ->assertDontSee('Stranger Alpha');

    Livewire::actingAs($me)->test(MessageThread::class, ['receiver' => $strangerB->id])
        ->assertDontSee('Private plans between strangers');
});

it('marks messages as read once the conversation is open', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Message::factory()->count(2)->create(['sender_id' => $other->id, 'receiver_id' => $me->id, 'is_read' => false]);

    Livewire::actingAs($me)->test(MessageThread::class, ['receiver' => $other->id]);

    expect(Message::where('receiver_id', $me->id)->where('is_read', false)->count())->toBe(0);
});

it('groups the conversation list with one entry per person and correct unread counts', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Message::factory()->count(3)->create(['sender_id' => $alice->id, 'receiver_id' => $me->id]);
    Message::factory()->create(['sender_id' => $me->id, 'receiver_id' => $alice->id]);
    Message::factory()->create(['sender_id' => $bob->id, 'receiver_id' => $me->id]);

    Livewire::actingAs($me)->test(MessageThread::class, ['receiver' => $bob->id])
        ->assertViewHas('conversations', function ($conversations) use ($alice, $bob) {
            return $conversations->count() === 2
                && $conversations[$alice->id]->unread_count === 3
                && $conversations[$bob->id]->unread_count === 0;
        });
});

it('ignores an attempt to select a conversation with oneself', function () {
    $me = User::factory()->create();

    Livewire::actingAs($me)->test(MessageThread::class)
        ->call('selectConversation', $me->id)
        ->assertSet('activeUserId', null);
});
