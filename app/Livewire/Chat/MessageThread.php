<?php

namespace App\Livewire\Chat;

use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class MessageThread extends Component
{
    private const MAX_MESSAGES_PER_MINUTE = 30;

    private const THREAD_MESSAGE_LIMIT = 200;

    private const SEARCH_RESULTS_LIMIT = 8;

    public ?int $activeUserId = null;

    public ?int $activeListingId = null;

    public ?int $activeTransactionId = null;

    public string $newMessage = '';

    public string $userSearch = '';

    public function mount(?int $receiver = null, ?int $listing = null)
    {
        $currentUserId = (int) Auth::id();

        if ($receiver !== null) {
            if ($receiver === $currentUserId) {
                return redirect()->route('chat.index')
                    ->with('warning', 'You cannot send messages to yourself.');
            }

            abort_unless(User::whereKey($receiver)->exists(), 404);

            $this->activeUserId = $receiver;
            $this->activeListingId = $listing && Listing::whereKey($listing)->exists() ? $listing : null;
            $this->activeTransactionId = $this->resolveTransactionId($currentUserId, $receiver);
            $this->prefillSuggestedMessage($currentUserId, $receiver);

            return null;
        }

        $latestMessage = Message::query()
            ->where(fn ($query) => $query->where('sender_id', $currentUserId)->orWhere('receiver_id', $currentUserId))
            ->latest('id')
            ->first();

        if ($latestMessage) {
            $this->activeUserId = $latestMessage->sender_id === $currentUserId
                ? $latestMessage->receiver_id
                : $latestMessage->sender_id;
            $this->activeListingId = $latestMessage->listing_id;
        }

        return null;
    }

    public function selectConversation(int $userId, ?int $listingId = null): void
    {
        if ($userId === (int) Auth::id() || ! User::whereKey($userId)->exists()) {
            return;
        }

        $this->activeUserId = $userId;
        $this->activeListingId = $listingId && Listing::whereKey($listingId)->exists() ? $listingId : null;
        $this->activeTransactionId = null;
        $this->newMessage = '';
        $this->userSearch = '';
        $this->resetValidation();
    }

    /**
     * Only honours a ?transaction= link when the signed-in user and the
     * person they're about to message are genuinely the two participants -
     * reuses TransactionPolicy so a crafted link can't attach someone else's
     * transaction to a message.
     */
    private function resolveTransactionId(int $currentUserId, int $receiverId): ?int
    {
        $transactionId = request()->integer('transaction') ?: null;

        if (! $transactionId) {
            return null;
        }

        $transaction = Transaction::find($transactionId);

        if (! $transaction || Gate::denies('view', $transaction)) {
            return null;
        }

        $participants = [$transaction->buyer_id, $transaction->seller_id];

        return in_array($currentUserId, $participants, true) && in_array($receiverId, $participants, true)
            ? $transaction->id
            : null;
    }

    /**
     * A first-message starting point, editable and never sent automatically -
     * only offered when nobody has messaged about this listing yet.
     */
    private function prefillSuggestedMessage(int $currentUserId, int $receiverId): void
    {
        if (! $this->activeListingId) {
            return;
        }

        $alreadyStarted = Message::query()
            ->where('listing_id', $this->activeListingId)
            ->where(function ($query) use ($currentUserId, $receiverId) {
                $query->where(fn ($inner) => $inner->where('sender_id', $currentUserId)->where('receiver_id', $receiverId))
                    ->orWhere(fn ($inner) => $inner->where('sender_id', $receiverId)->where('receiver_id', $currentUserId));
            })
            ->exists();

        if ($alreadyStarted) {
            return;
        }

        $title = Listing::whereKey($this->activeListingId)->value('title');

        if ($title) {
            $this->newMessage = "Hi, about the {$title} — when can we meet for the handover?";
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function searchResults(int $currentUserId): Collection
    {
        $term = trim($this->userSearch);

        if ($term === '') {
            return collect();
        }

        $escaped = addcslashes($term, '\\%_');

        return User::query()
            ->where('id', '!=', $currentUserId)
            ->where('name', 'like', "%{$escaped}%")
            ->orderBy('name')
            ->limit(self::SEARCH_RESULTS_LIMIT)
            ->get(['id', 'name', 'is_verified', 'avatar_path']);
    }

    public function markAsRead(): void
    {
        if ($this->activeUserId) {
            Message::where('sender_id', $this->activeUserId)
                ->where('receiver_id', Auth::id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }
    }

    public function sendMessage(): void
    {
        if (Auth::user()->isAwaitingEmailVerification()) {
            $this->addError('newMessage', 'Please verify your email address before messaging other students.');

            return;
        }

        $this->newMessage = trim($this->newMessage);

        $this->validate(
            ['newMessage' => ['required', 'string', 'max:1000']],
            [
                'newMessage.required' => 'Type a message before sending.',
                'newMessage.max' => 'Messages can be at most 1,000 characters long.',
            ]
        );

        $currentUserId = (int) Auth::id();

        if (! $this->activeUserId || $this->activeUserId === $currentUserId || ! User::whereKey($this->activeUserId)->exists()) {
            $this->addError('newMessage', 'Choose someone to message first.');

            return;
        }

        $throttleKey = 'chat-send|'.$currentUserId;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_MESSAGES_PER_MINUTE)) {
            $this->addError('newMessage', 'You are sending messages too quickly. Please wait a moment.');

            return;
        }

        RateLimiter::hit($throttleKey);

        Message::create([
            'sender_id' => $currentUserId,
            'receiver_id' => $this->activeUserId,
            'listing_id' => $this->activeListingId,
            'transaction_id' => $this->activeTransactionId,
            'message' => $this->newMessage,
            'is_read' => false,
        ]);

        if ($this->activeListingId) {
            ListingEvent::create([
                'listing_id' => $this->activeListingId,
                'user_id' => $currentUserId,
                'type' => ListingEvent::TYPE_MESSAGE,
                'created_at' => now(),
            ]);
        }

        $this->newMessage = '';
        $this->dispatch('message-sent');
    }

    public function render()
    {
        $currentUserId = (int) Auth::id();

        $this->markAsRead();

        $activeUser = $this->activeUserId ? User::find($this->activeUserId) : null;
        $activeListing = $this->activeListingId ? Listing::find($this->activeListingId) : null;
        $activeTransaction = $this->activeTransactionId ? Transaction::find($this->activeTransactionId) : null;

        $activeMessages = $activeUser
            ? Message::query()
                ->where(function ($query) use ($currentUserId, $activeUser) {
                    $query->where(fn ($inner) => $inner->where('sender_id', $currentUserId)->where('receiver_id', $activeUser->id))
                        ->orWhere(fn ($inner) => $inner->where('sender_id', $activeUser->id)->where('receiver_id', $currentUserId));
                })
                ->latest('id')
                ->limit(self::THREAD_MESSAGE_LIMIT)
                ->get()
                ->reverse()
                ->values()
            : collect();

        return view('livewire.chat.message-thread', [
            'conversations' => $this->conversations($currentUserId),
            'activeMessages' => $activeMessages,
            'activeUser' => $activeUser,
            'activeListing' => $activeListing,
            'activeTransaction' => $activeTransaction,
            'searchResults' => $this->searchResults($currentUserId),
        ])->layout('layouts.app', ['title' => 'Messages - UniMarket']);
    }

    /**
     * One entry per person the user has chatted with, newest conversation first.
     *
     * @return Collection<int, object{user: User, latest_message: Message, unread_count: int}>
     */
    private function conversations(int $currentUserId): Collection
    {
        $latestMessageIds = Message::query()
            ->where(fn ($query) => $query->where('sender_id', $currentUserId)->orWhere('receiver_id', $currentUserId))
            ->selectRaw('MAX(id) as id')
            ->groupByRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END', [$currentUserId])
            ->pluck('id');

        $unreadCounts = Message::query()
            ->where('receiver_id', $currentUserId)
            ->where('is_read', false)
            ->selectRaw('sender_id, COUNT(*) as total')
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        return Message::query()
            ->with(['sender:id,name,is_verified,avatar_path', 'receiver:id,name,is_verified,avatar_path'])
            ->whereIn('id', $latestMessageIds)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(function (Message $message) use ($currentUserId, $unreadCounts) {
                $partner = $message->sender_id === $currentUserId ? $message->receiver : $message->sender;

                return [$partner->id => (object) [
                    'user' => $partner,
                    'latest_message' => $message,
                    'unread_count' => (int) ($unreadCounts[$partner->id] ?? 0),
                ]];
            });
    }
}
