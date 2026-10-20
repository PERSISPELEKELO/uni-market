<?php

use App\Livewire\HelpPanel;
use App\Models\SupportMessage;
use App\Models\User;
use Livewire\Livewire;

it('shows the Help trigger to a signed-in student, without the FAQ text until opened', function () {
    $this->actingAs(User::factory()->create())->get(route('listings.index'))
        ->assertOk()
        ->assertSee('Help')
        ->assertDontSee('How do I reserve an item?');
});

it('never shows the Help trigger to a guest', function () {
    $this->get(route('how-it-works'))
        ->assertOk()
        ->assertDontSee('open-help-panel', false);
});

it('reveals the FAQ and quick links once the panel is opened', function () {
    Livewire::test(HelpPanel::class)
        ->call('openPanel')
        ->assertSee('How do I reserve an item?')
        ->assertSee('Buying an item');
});

it('lets a signed-in student send a support message', function () {
    $student = User::factory()->create();

    Livewire::actingAs($student)->test(HelpPanel::class)
        ->set('subject', 'Cannot upload my student ID')
        ->set('message', 'The upload button does not respond when I tap it on my phone.')
        ->call('submitContactForm');

    $this->assertDatabaseHas('support_messages', [
        'user_id' => $student->id,
        'subject' => 'Cannot upload my student ID',
        'status' => SupportMessage::STATUS_OPEN,
    ]);
});

it('validates the subject and message before sending', function () {
    $student = User::factory()->create();

    Livewire::actingAs($student)->test(HelpPanel::class)
        ->set('subject', '')
        ->set('message', 'too short')
        ->call('submitContactForm')
        ->assertHasErrors(['subject' => 'required', 'message' => 'min']);

    expect(SupportMessage::count())->toBe(0);
});

it('sends a guest to log in instead of saving a support message', function () {
    Livewire::test(HelpPanel::class)
        ->set('subject', 'Help')
        ->set('message', 'This is a long enough message to pass validation.')
        ->call('submitContactForm')
        ->assertRedirect(route('login'));

    expect(SupportMessage::count())->toBe(0);
});

it('only ever attributes a message to the signed-in student', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();

    Livewire::actingAs($attacker)->test(HelpPanel::class)
        ->set('subject', 'Help')
        ->set('message', 'This is a long enough message to pass validation.')
        ->call('submitContactForm');

    expect(SupportMessage::first()->user_id)->toBe($attacker->id)->not->toBe($victim->id);
});
