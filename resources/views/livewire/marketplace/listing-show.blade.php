<div>
    <!-- Back to Marketplace Navigation -->
    <div class="mb-6">
        <a href="{{ route('listings.index') }}" class="inline-flex items-center space-x-1.5 text-xs font-medium text-slate-500 hover:text-[#1E293B] transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Back to Marketplace</span>
        </a>
    </div>

    <!-- Grid Container: 2-Column layout for desktop (Images left, details right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Left Column: Image Gallery Carousel & Thumbnails (7 Cols) -->
        <div class="lg:col-span-7 space-y-4">
            <!-- Active Image View -->
            <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm h-96 sm:h-[450px] relative flex items-center justify-center">
                @php
                    $images = is_array($listing->images) && count($listing->images) > 0
                        ? array_map(fn($img) => asset('storage/' . $img), $listing->images)
                        : ['https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=1000&q=80'];
                    $currentImage = $images[$activeImageIndex] ?? $images[0];
                @endphp
                <img src="{{ $currentImage }}" alt="{{ $listing->title }}" class="w-full h-full object-cover" />

                <!-- Item Status Badge overlay if pending or sold -->
                @if($listing->status !== 'active')
                    <div class="absolute top-4 right-4 bg-[#D97706] text-white px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider shadow-sm">
                        {{ strtoupper($listing->status) }}
                    </div>
                @endif
            </div>

            <!-- Thumbnail Carousel Selector -->
            @if(count($images) > 1)
                <div class="flex items-center space-x-3 overflow-x-auto pb-2">
                    @foreach($images as $index => $imgUrl)
                        <button
                            wire:click="setActiveImage({{ $index }})"
                            class="w-20 h-20 rounded-lg overflow-hidden border-2 transition flex-shrink-0 {{ $activeImageIndex === $index ? 'border-[#312E81] ring-2 ring-[#312E81]/20' : 'border-slate-200 opacity-70 hover:opacity-100' }}"
                        >
                            <img src="{{ $imgUrl }}" class="w-full h-full object-cover" alt="Thumbnail {{ $index + 1 }}" />
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right Column: Product Details & Purchase Card (5 Cols) -->
        <div class="lg:col-span-5 space-y-6">

            <!-- Primary Info Card -->
            <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <span class="bg-slate-100 text-slate-700 text-xs font-medium px-2.5 py-1 rounded-md uppercase tracking-wider">
                        {{ $listing->category->name }}
                    </span>
                    <span class="text-xs font-regular text-slate-400">
                        Listed {{ $listing->created_at->diffForHumans() }}
                    </span>
                </div>

                <h1 class="text-2xl font-semibold text-[#0F172A] tracking-tight leading-snug">
                    {{ $listing->title }}
                </h1>

                <!-- Price Tag (Emerald Green #059669) -->
                <div class="flex items-baseline space-x-3">
                    <span class="text-3xl font-semibold text-[#059669]">
                        K{{ number_format($listing->price, 2) }}
                    </span>
                    <span class="text-xs font-medium text-slate-500 bg-slate-50 border border-slate-200 px-2 py-0.5 rounded capitalize">
                        Condition: {{ str_replace('_', ' ', $listing->condition) }}
                    </span>
                </div>

                <!-- Product Description -->
                <div class="pt-4 border-t border-slate-100">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Item Description</h3>
                    <p class="text-sm font-regular text-[#0F172A] leading-relaxed whitespace-pre-line">
                        {{ $listing->description }}
                    </p>
                </div>
            </div>

            <!-- Seller Information Card -->
            <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Seller Information</h3>

                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-11 h-11 rounded-full bg-[#1E293B] text-white flex items-center justify-center font-medium text-base">
                            {{ substr($listing->seller->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="flex items-center space-x-1.5">
                                <span class="text-sm font-semibold text-[#0F172A]">{{ $listing->seller->name }}</span>
                                @if($listing->seller->is_verified)
                                    <span class="bg-[#059669]/10 text-[#059669] border border-[#059669]/20 text-[10px] font-medium px-1.5 py-0.5 rounded-full flex items-center space-x-0.5">
                                        <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span>Official Student</span>
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs font-regular text-slate-500 mt-0.5">
                                Student ID: {{ $listing->seller->student_id ?? 'Verified Campus Member' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Call to Actions -->
                <div class="pt-4 space-y-3">
                    @if(auth()->id() !== $listing->user_id)
                        @if($listing->status === 'active')
                            <button
                                wire:click="initiatePurchase"
                                class="w-full bg-[#1E293B] hover:bg-[#312E81] text-white font-medium py-3 px-4 rounded-lg text-sm transition shadow-sm flex items-center justify-center space-x-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>Initiate Purchase / Reserve Item</span>
                            </button>
                        @else
                            <button disabled class="w-full bg-slate-100 text-slate-400 font-medium py-3 px-4 rounded-lg text-sm cursor-not-allowed">
                                Item {{ ucfirst($listing->status) }}
                            </button>
                        @endif

                        <button
                            wire:click="contactSeller"
                            class="w-full bg-white border border-slate-200 hover:bg-slate-50 text-[#0F172A] font-medium py-2.5 px-4 rounded-lg text-sm transition flex items-center justify-center space-x-2"
                        >
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <span>Chat with Seller</span>
                        </button>
                    @else
                        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-center text-xs font-medium text-slate-600">
                            You are the seller of this listing.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Escrow Safety Guarantee Note -->
            <div class="bg-[#059669]/5 border border-[#059669]/20 rounded-lg p-4 flex items-start space-x-3 text-xs">
                <svg class="w-5 h-5 text-[#059669] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <div>
                    <span class="font-semibold text-[#059669]">Campus Escrow Protection</span>
                    <p class="text-slate-600 font-regular mt-0.5">
                        Transactions track through 4 stages. Funds are held safely until you inspect the item in person and confirm completion.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
