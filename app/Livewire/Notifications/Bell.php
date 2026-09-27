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
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $notification = $user->notifications()->whereKey($notificationId)->first();

        if (! $notification) {
            return null;
        }

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('listings.index'));
    }

    public function markAllAsRead(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();
    }

    /**
     * The bell only ever renders for a logged-in user (the layout gates it
     * behind an auth check), but it also polls every 20 seconds - if the
     * session expires or the user logs out in another tab while this
     * component is still mounted, that next poll runs with no authenticated
     * user, so this still needs to fail safe rather than crash.
     */
    public function render()
    {
        $user = Auth::user();

        return view('livewire.notifications.bell', [
            'notifications' => $user ? $user->notifications()->latest()->limit(self::RECENT_LIMIT)->get() : collect(),
            'unreadCount' => $user ? $user->unreadNotifications()->count() : 0,
        ]);
    }
}
