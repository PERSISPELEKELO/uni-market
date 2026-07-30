<?php

namespace App\Livewire\Chat;

use Livewire\Component;
use App\Models\Message;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MessageThread extends Component
{
    public ?int $activeUserId = null;
    public ?int $activeListingId = null;
    public string $newMessage = '';

    public function mount(?int $receiver = null, ?int $listing = null): void
    {
        $this->activeUserId = $receiver;
        $this->activeListingId = $listing;

        // If no receiver provided, pick the most recent conversation
        if (!$this->activeUserId) {
            $latestMessage = Message::where('sender_id', Auth::id())
                ->orWhere('receiver_id', Auth::id())
                ->latest()
                ->first();

            if ($latestMessage) {
                $this->activeUserId = ($latestMessage->sender_id === Auth::id())
                    ? $latestMessage->receiver_id
                    : $latestMessage->sender_id;
                $this->activeListingId = $latestMessage->listing_id;
            }
        }

        $this->markAsRead();
    }

    public function selectConversation(int $userId, ?int $listingId = null): void
    {
        $this->activeUserId = $userId;
        $this->activeListingId = $listingId;
        $this->markAsRead();
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
        if (empty(trim($this->newMessage)) || !$this->activeUserId) {
            return;
        }

        Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $this->activeUserId,
            'listing_id' => $this->activeListingId,
            'message' => trim($this->newMessage),
            'is_read' => false,
        ]);

        $this->newMessage = '';
        $this->dispatch('message-sent');
    }

    public function render()
    {
        $currentUserId = Auth::id();

        // Get list of unique users the active user has chatted with
        $messages = Message::where('sender_id', $currentUserId)
            ->orWhere('receiver_id', $currentUserId)
            ->latest()
            ->get();

        $conversationPartners = collect();
        foreach ($messages as $msg) {
            $partnerId = ($msg->sender_id === $currentUserId) ? $msg->receiver_id : $msg->sender_id;
            if (!$conversationPartners->has($partnerId)) {
                $partner = User::find($partnerId);
                if ($partner) {
                    $unreadCount = Message::where('sender_id', $partnerId)
                        ->where('receiver_id', $currentUserId)
                        ->where('is_read', false)
                        ->count();

                    $conversationPartners->put($partnerId, (object) [
                        'user' => $partner,
                        'latest_message' => $msg,
                        'unread_count' => $unreadCount,
                    ]);
                }
            }
        }

        // Active chat thread messages
        $activeMessages = collect();
        $activeUser = null;
        $activeListing = null;

        if ($this->activeUserId) {
            $activeUser = User::find($this->activeUserId);
            $this->markAsRead();

            $activeMessages = Message::where(function ($q) {
                $q->where('sender_id', Auth::id())
                  ->where('receiver_id', $this->activeUserId);
            })->orWhere(function ($q) {
                $q->where('sender_id', $this->activeUserId)
                  ->where('receiver_id', Auth::id());
            })->orderBy('created_at', 'asc')->get();

            if ($this->activeListingId) {
                $activeListing = Listing::find($this->activeListingId);
            }
        }

        return view('livewire.chat.message-thread', [
            'conversations' => $conversationPartners,
            'activeMessages' => $activeMessages,
            'activeUser' => $activeUser,
            'activeListing' => $activeListing,
        ])->layout('layouts.app', ['title' => 'Messages - UniMarket']);
    }
}
