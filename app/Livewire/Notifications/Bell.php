<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Bell extends Component
{
    private const RECENT_LIMIT = 10;

    /**
     * Mark one notification read and send the viewer to what it's about.
     */
    public function open(string $notificationId)
    {
        $notification = Auth::user()->notifications()->whereKey($notificationId)->first();

        if (! $notification) {
            return null;
        }

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('listings.index'));
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.notifications.bell', [
            'notifications' => $user->notifications()->latest()->limit(self::RECENT_LIMIT)->get(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
