<div class="h-[calc(100vh-12rem)] min-h-[550px] bg-white border border-slate-200 rounded-lg shadow-sm flex overflow-hidden">

    <!-- Left Panel: Conversation List (35% width desktop) -->
    <div class="w-full md:w-80 lg:w-96 border-r border-slate-200 flex flex-col bg-[#F8FAFC]">
        <!-- Panel Header -->
        <div class="p-4 border-b border-slate-200 bg-white">
            <h2 class="text-base font-semibold text-[#0F172A] tracking-tight">Messages</h2>
            <p class="text-xs text-slate-500 font-regular mt-0.5">Secure buyer-seller discussions</p>
        </div>

        <!-- Conversations Scrollable Area -->
        <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
            @forelse($conversations as $partnerId => $item)
                <button
                    wire:click="selectConversation({{ $partnerId }}, {{ $item->latest_message->listing_id }})"
                    class="w-full p-4 text-left hover:bg-slate-100 transition flex items-start space-x-3 {{ $activeUserId === $partnerId ? 'bg-white border-l-4 border-[#312E81]' : '' }}"
                >
                    <!-- User Avatar with Verified Dot -->
                    <div class="relative flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-[#1E293B] text-white flex items-center justify-center font-medium text-sm">
                            {{ substr($item->user->name, 0, 1) }}
                        </div>
                        @if($item->user->is_verified)
                            <span class="absolute -bottom-0.5 -right-0.5 bg-[#059669] text-white p-0.5 rounded-full border-2 border-white">
                                <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        @endif
                    </div>

                    <!-- Conversation Snippet -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-[#0F172A] truncate">
                                {{ $item->user->name }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-regular">
                                {{ $item->latest_message->created_at->diffForHumans(null, true) }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-500 font-regular truncate mt-1">
                            {{ $item->latest_message->message }}
                        </p>
                    </div>

                    <!-- Unread Pill -->
                    @if($item->unread_count > 0)
                        <span class="bg-[#059669] text-white text-[10px] font-semibold px-2 py-0.5 rounded-full">
                            {{ $item->unread_count }}
                        </span>
                    @endif
                </button>
            @empty
                <div class="p-8 text-center text-slate-400 text-xs font-regular">
                    No active conversations yet. Reach out to sellers directly from listing details!
                </div>
            @endforelse
        </div>
    </div>

    <!-- Right Panel: Chat Thread Window & Context Bar (65% width) -->
    <div class="flex-1 flex flex-col bg-white" wire:poll.3s="markAsRead">
        @if($activeUser)
            <!-- Top Context Bar (Listing thumbnail, title, price, reserve CTA) -->
            <div class="p-3 sm:p-4 border-b border-slate-200 bg-white flex items-center justify-between shadow-xs">
                <!-- User Info -->
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-full bg-[#1E293B] text-white flex items-center justify-center font-medium text-xs">
                        {{ substr($activeUser->name, 0, 1) }}
                    </div>
                    <div>
                        <div class="flex items-center space-x-1.5">
                            <h3 class="text-sm font-semibold text-[#0F172A]">{{ $activeUser->name }}</h3>
                            @if($activeUser->is_verified)
                                <span class="bg-[#059669]/10 text-[#059669] border border-[#059669]/20 text-[10px] font-medium px-1.5 py-0.2 rounded-full">
                                    Official Student
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-400 font-regular">Campus Marketplace Chat</p>
                    </div>
                </div>

                <!-- Context Listing Bar -->
                @if($activeListing)
                    <div class="hidden sm:flex items-center space-x-3 bg-slate-50 border border-slate-200 p-2 rounded-lg text-xs">
                        <div class="w-10 h-10 rounded bg-slate-200 overflow-hidden flex-shrink-0">
                            @php
                                $firstImg = is_array($activeListing->images) && count($activeListing->images) > 0 ? asset('storage/' . $activeListing->images[0]) : 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=200&q=80';
                            @endphp
                            <img src="{{ $firstImg }}" class="w-full h-full object-cover" />
                        </div>
                        <div>
                            <div class="font-medium text-[#0F172A] line-clamp-1 max-w-[150px]">{{ $activeListing->title }}</div>
                            <div class="text-[#059669] font-semibold">K{{ number_format($activeListing->price, 2) }}</div>
                        </div>

                        <a
                            href="{{ route('listings.show', $activeListing->id) }}"
                            class="bg-[#1E293B] hover:bg-[#312E81] text-white font-medium px-3 py-1.5 rounded text-xs transition"
                        >
                            Reserve Item
                        </a>
                    </div>
                @endif
            </div>

            <!-- Chat Messages Scroll Container -->
            <div class="flex-1 p-4 overflow-y-auto space-y-3 bg-[#F8FAFC]" id="chatContainer">
                @forelse($activeMessages as $msg)
                    @php $isMe = $msg->sender_id === auth()->id(); @endphp
                    <div class="flex flex-col {{ $isMe ? 'items-end' : 'items-start' }}">
                        <div class="max-w-[75%] px-4 py-2.5 rounded-lg text-sm shadow-xs {{ $isMe ? 'bg-[#1E293B] text-white rounded-br-none' : 'bg-white border border-slate-200 text-[#0F172A] rounded-bl-none' }}">
                            <p class="font-regular leading-relaxed">{{ $msg->message }}</p>
                        </div>
                        <span class="text-[10px] text-slate-400 font-regular mt-1 px-1">
                            {{ $msg->created_at->format('g:i A') }}
                        </span>
                    </div>
                @empty
                    <div class="text-center text-slate-400 text-xs font-regular py-12">
                        Start the conversation regarding this listing.
                    </div>
                @endforelse
            </div>

            <!-- Message Input Area -->
            <form wire:submit.prevent="sendMessage" class="p-3 sm:p-4 bg-white border-t border-slate-200 flex items-center space-x-3">
                <input
                    type="text"
                    wire:model="newMessage"
                    placeholder="Type a secure message..."
                    class="flex-1 bg-[#F8FAFC] border border-slate-200 rounded-lg px-4 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669] focus:border-transparent transition"
                />
                <button
                    type="submit"
                    class="bg-[#1E293B] hover:bg-[#312E81] text-white font-medium px-4 py-2.5 rounded-lg text-sm transition shadow-sm flex items-center space-x-1.5"
                >
                    <span>Send</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
        @else
            <!-- Empty State when no conversation selected -->
            <div class="flex-1 flex flex-col items-center justify-center text-center p-8 bg-[#F8FAFC]">
                <div class="w-12 h-12 rounded-full bg-slate-200 text-slate-400 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-[#0F172A]">Select a Conversation</h3>
                <p class="text-xs text-slate-500 font-regular mt-1">Choose a student chat thread from the left panel to begin messaging.</p>
            </div>
        @endif
    </div>
</div>
