<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SupportMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The floating Help panel rendered once in layouts.app: a searchable FAQ,
 * quick links into "How UniMarket works", and a "Contact support" form -
 * deliberately not a live chat (see the brief this was built from).
 *
 * The FAQ text is only rendered once $open is true, fetched via a Livewire
 * round-trip rather than embedded in every page's initial HTML. This isn't
 * just a payload-size nicety: the FAQ answers quote real product copy
 * ("Reserve this item", "48-hour inspection window") that several existing
 * page tests assert is ABSENT in specific contexts (e.g. a seller viewing
 * their own listing) - embedding it unconditionally broke those tests by
 * making the phrase "present somewhere on the page" even though the actual
 * feature it was asserting on behaved correctly.
 */
class HelpPanel extends Component
{
    public bool $open = false;

    public string $subject = '';

    public string $message = '';

    #[On('open-help-panel')]
    public function openPanel(): void
    {
        $this->open = true;
    }

    public function closePanel(): void
    {
        $this->open = false;
    }

    public function submitContactForm()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $this->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'subject.required' => 'Please enter a subject.',
            'message.required' => 'Please describe what you need help with.',
            'message.min' => 'Please give us a few more details.',
        ]);

        SupportMessage::create([
            'user_id' => Auth::id(),
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => SupportMessage::STATUS_OPEN,
        ]);

        $this->reset('subject', 'message');
        $this->open = false;
        $this->dispatch('notify', type: 'success', message: "Thanks - we've received your message and will get back to you by email.");
    }

    public function render()
    {
        return view('livewire.help-panel', [
            'faqs' => $this->open ? config('help.faqs') : [],
            'quickLinks' => config('help.quick_links'),
        ]);
    }
}
