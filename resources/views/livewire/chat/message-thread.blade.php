<div
    wire:poll.5s
    x-data="{ pane: '{{ $activeUser ? 'thread' : 'list' }}' }"
    class="card flex h-[calc(100dvh-11rem)] min-h-[30rem] overflow-hidden"
>

    <section
        class="w-full flex-col border-r border-slate-200 bg-slate-50 md:flex md:w-80 lg:w-96"
        x-bind:class="pane === 'list' ? 'flex' : 'hidden'"
        aria-labelledby="conversations-heading"
    >
        <div class="border-b border-slate-200 bg-white p-4">
            <h1 id="conversations-heading" class="text-lg font-bold tracking-tight text-ink">Messages</h1>
            <p class="text-xs text-slate-600">Chat with buyers and sellers on campus</p>
        </div>

        <ul class="flex-1 divide-y divide-slate-200 overflow-y-auto">
            @forelse ($conversations as $partnerId => $item)
                <li wire:key="conversation-{{ $partnerId }}">
                    <button
                        type="button"
                        wire:click="selectConversation({{ $partnerId }}, {{ $item->latest_message->listing_id ?? 'null' }})"
                        x-on:click="pane = 'thread'"
                        aria-current="{{ $activeUserId === $partnerId ? 'true' : 'false' }}"
                        @class(['flex min-h-16 w-full items-start gap-3 p-4 text-left transition-colors hover:bg-white', 'bg-white border-l-4 border-brand-700' => $activeUserId === $partnerId])
                    >
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-700 text-sm font-semibold text-white" aria-hidden="true">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($item->user->name, 0, 1)) }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-ink">{{ $item->user->name }}</span>
                                <span class="flex-shrink-0 text-xs text-slate-600">{{ $item->latest_message->created_at->diffForHumans(short: true) }}</span>
                            </span>
                            <span class="mt-0.5 block truncate text-sm text-slate-600">{{ $item->latest_message->message }}</span>
                        </span>

                        @if ($item->unread_count > 0)
                            <span class="badge flex-shrink-0 border-accent-700 bg-accent-700 text-white">
                                {{ $item->unread_count }}<span class="sr-only"> unread</span>
                            </span>
                        @endif
                    </button>
                </li>
            @empty
                <li class="p-8 text-center text-sm text-slate-600">
                    No conversations yet. Open a listing and choose "Message seller" to start one.
                </li>
            @endforelse
        </ul>
    </section>

    <section class="min-w-0 flex-1 flex-col bg-white md:flex" x-bind:class="pane === 'thread' ? 'flex' : 'hidden'" aria-label="Conversation">
        @if ($activeUser)
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-3 sm:p-4">
                <button type="button" x-on:click="pane = 'list'" class="btn btn-secondary btn-sm min-h-11 min-w-11 px-2 md:hidden">
                    <x-app-icon name="arrow-left" class="h-5 w-5" />
                    <span class="sr-only">Back to conversations</span>
                </button>

                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-semibold text-white" aria-hidden="true">
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($activeUser->name, 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-sm font-semibold text-ink">{{ $activeUser->name }}</h2>
                    @if ($activeUser->is_verified)
                        <span class="badge badge-success px-2 py-0 text-[11px]"><x-app-icon name="check-circle" class="h-3 w-3" /> Official Student</span>
                    @endif
                </div>

                @if ($activeListing)
                    <a href="{{ route('listings.show', $activeListing) }}" class="flex w-full min-w-0 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-2 hover:bg-slate-100 sm:w-auto sm:max-w-xs">
                        <x-listing-image :src="$activeListing->cover_image_url" :alt="$activeListing->title" :label="false" class="h-10 w-10 flex-shrink-0 rounded" />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-ink">{{ $activeListing->title }}</span>
                            <span class="block text-sm font-bold text-accent-700">K{{ number_format($activeListing->price, 2) }}</span>
                        </span>
                    </a>
                @endif
            </div>

            <div
                class="flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4"
                role="log"
                aria-live="polite"
                aria-label="Messages with {{ $activeUser->name }}"
                x-data="{ scroll() { this.$nextTick(() => { this.$el.scrollTop = this.$el.scrollHeight; }); } }"
                x-init="scroll()"
                x-on:message-sent.window="scroll()"
            >
                @forelse ($activeMessages as $msg)
                    @php $isMe = $msg->sender_id === auth()->id(); @endphp
                    <div wire:key="message-{{ $msg->id }}" @class(['flex flex-col', 'items-end' => $isMe, 'items-start' => ! $isMe])>
                        <div @class(['max-w-[85%] break-words rounded-2xl px-4 py-2.5 text-sm sm:max-w-[75%]', 'rounded-br-md bg-brand-700 text-white' => $isMe, 'rounded-bl-md border border-slate-200 bg-white text-ink' => ! $isMe])>
                            <p class="whitespace-pre-line leading-relaxed">{{ $msg->message }}</p>
                        </div>
                        <span class="mt-1 px-1 text-xs text-slate-600">
                            <span class="sr-only">{{ $isMe ? 'You' : $activeUser->name }} at </span>{{ $msg->created_at->format('g:i A') }}
                        </span>
                    </div>
                @empty
                    <p class="py-12 text-center text-sm text-slate-600">Say hello and ask about the item to start the conversation.</p>
                @endforelse
            </div>

            <form wire:submit="sendMessage" novalidate class="border-t border-slate-200 bg-white p-3 sm:p-4">
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <label for="newMessage" class="sr-only">Your message</label>
                        <input
                            id="newMessage"
                            type="text"
                            wire:model="newMessage"
                            maxlength="1000"
                            autocomplete="off"
                            placeholder="Type a message..."
                            class="form-input"
                            @error('newMessage') aria-invalid="true" aria-describedby="newMessage-error" @enderror
                        />
                        <x-form-error name="newMessage" />
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="sendMessage">
                        <span class="hidden sm:inline">Send</span>
                        <x-app-icon name="send" class="h-5 w-5" />
                        <span class="sr-only sm:hidden">Send message</span>
                    </button>
                </div>
            </form>
        @else
            <div class="flex flex-1 flex-col items-center justify-center bg-slate-50 p-8 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                    <x-app-icon name="chat" class="h-6 w-6" />
                </div>
                <h2 class="mt-3 text-base font-semibold text-ink">No conversation selected</h2>
                <p class="mt-1 max-w-xs text-sm text-slate-600">Choose a conversation from the list, or message a seller from any listing.</p>
                <a href="{{ route('listings.index') }}" class="btn btn-primary mt-4">Browse the marketplace</a>
            </div>
        @endif
    </section>
</div>
