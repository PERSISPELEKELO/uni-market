<div class="relative" wire:poll.20s x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <button
        type="button"
        class="btn btn-secondary btn-sm relative min-h-11 min-w-11 px-2 sm:min-h-11"
        x-on:click="open = !open"
        x-bind:aria-expanded="open"
        aria-haspopup="true"
    >
        <x-app-icon name="bell" class="h-5 w-5" />
        <span class="sr-only">Notifications</span>
        @if ($unreadCount > 0)
            <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-danger-700 px-1 text-[10px] font-bold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-cloak x-show="open" x-transition.opacity class="absolute right-0 z-30 mt-2 w-80 max-w-[90vw] origin-top-right rounded-xl border border-slate-200 bg-white shadow-lg dark:bg-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-ink">Notifications</h2>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-semibold text-brand-800 dark:text-brand-300 hover:underline">Mark all as read</button>
            @endif
        </div>

        <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($notifications as $notification)
                <li wire:key="notification-{{ $notification->id }}">
                    <button
                        type="button"
                        wire:click="open('{{ $notification->id }}')"
                        x-on:click="open = false"
                        @class(['flex w-full items-start gap-2 px-4 py-3 text-left hover:bg-slate-50', 'bg-brand-50/60 dark:bg-brand-500/10' => is_null($notification->read_at)])
                    >
                        <span @class(['mt-1.5 h-2 w-2 flex-shrink-0 rounded-full', 'bg-brand-700' => is_null($notification->read_at), 'bg-transparent' => ! is_null($notification->read_at)])></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-ink">{{ $notification->data['title'] ?? 'Notification' }}</span>
                            <span class="mt-0.5 block truncate text-xs text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-500">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-600">You have no notifications yet.</li>
            @endforelse
        </ul>
    </div>
</div>
