<div>
    <!-- Hero / Discovery Banner -->
    <div class="bg-gradient-to-r from-[#1E293B] to-[#312E81] text-white rounded-xl p-8 mb-8 shadow-sm">
        <div class="max-w-2xl">
            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight leading-snug">
                Buy & Sell Safely Within Your Campus Community
            </h1>
            <p class="text-slate-300 text-sm font-regular mt-2">
                Verified student profiles, instant messaging, and secure escrow lifecycle.
            </p>

            <!-- Search Bar Input -->
            <div class="mt-6 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search textbooks, electronics, dorm gear..."
                    class="w-full pl-10 pr-4 py-3 bg-white text-[#0F172A] border-0 rounded-lg shadow-sm focus:ring-2 focus:ring-[#059669] text-sm font-regular placeholder-slate-400"
                />
            </div>
        </div>
    </div>

    <!-- Category Chip Selector Pills -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-4 mb-6 scrollbar-none">
        <button
            wire:click="selectCategory(null)"
            class="px-4 py-2 rounded-full text-xs font-medium transition flex-shrink-0 {{ is_null($selectedCategory) ? 'bg-[#1E293B] text-white shadow-sm' : 'bg-white border border-slate-200 text-[#0F172A] hover:bg-slate-50' }}"
        >
            All Items
        </button>

        @foreach($categories as $category)
            <button
                wire:click="selectCategory({{ $category->id }})"
                class="px-4 py-2 rounded-full text-xs font-medium transition flex-shrink-0 flex items-center space-x-1.5 {{ $selectedCategory === $category->id ? 'bg-[#1E293B] text-white shadow-sm' : 'bg-white border border-slate-200 text-[#0F172A] hover:bg-slate-50' }}"
            >
                <span>{{ $category->name }}</span>
                <span class="text-[10px] opacity-75">({{ $category->listings_count }})</span>
            </button>
        @endforeach
    </div>

    <!-- Toolbar: Condition Filters & Sort -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-slate-200">
        <!-- Condition Pills -->
        <div class="flex items-center space-x-2 text-xs font-medium">
            <span class="text-slate-500 font-regular">Condition:</span>
            @foreach(['new' => 'New', 'like_new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair'] as $key => $label)
                <button
                    wire:click="setCondition('{{ $key }}')"
                    class="px-3 py-1 rounded-md transition {{ $conditionFilter === $key ? 'bg-[#059669] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <!-- Sort Select -->
        <div class="flex items-center space-x-2 text-xs">
            <span class="text-slate-500 font-regular">Sort by:</span>
            <select wire:model.live="sortBy" class="bg-white border border-slate-200 text-[#0F172A] rounded-lg text-xs py-1.5 px-3 font-medium focus:ring-[#059669]">
                <option value="latest">Newest First</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
            </select>
        </div>
    </div>

    <!-- Product Grid (Responsive 3-column desktop, 1-column mobile) -->
    @if($listings->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($listings as $listing)
                <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between group">
                    <div>
                        <!-- Image Container with Aspect Ratio & Condition Pill -->
                        <div class="relative h-48 bg-slate-100 overflow-hidden">
                            @php
                                $firstImage = is_array($listing->images) && count($listing->images) > 0 ? asset('storage/' . $listing->images[0]) : 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=600&q=80';
                            @endphp
                            <img
                                src="{{ $firstImage }}"
                                alt="{{ $listing->title }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                            />
                            <!-- Condition Badge -->
                            <div class="absolute top-3 left-3 bg-white/95 backdrop-blur-sm border border-slate-200 px-2.5 py-1 rounded-md text-[11px] font-medium text-[#0F172A] capitalize shadow-sm">
                                {{ str_replace('_', ' ', $listing->condition) }}
                            </div>
                        </div>

                        <!-- Listing Body -->
                        <div class="p-5">
                            <!-- Category Name -->
                            <div class="text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-1">
                                {{ $listing->category->name }}
                            </div>

                            <!-- Title -->
                            <h3 class="text-base font-semibold text-[#0F172A] line-clamp-1 group-hover:text-[#312E81] transition">
                                <a href="{{ route('listings.show', $listing->id) }}">
                                    {{ $listing->title }}
                                </a>
                            </h3>

                            <!-- Price Tag (Emerald Green #059669) -->
                            <div class="mt-3 text-lg font-semibold text-[#059669]">
                                K{{ number_format($listing->price, 2) }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer Details: Seller Badge & Relative Age -->
                    <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-1.5">
                            <span class="font-medium text-[#0F172A]">{{ $listing->seller->name }}</span>
                            @if($listing->seller->is_verified)
                                <span class="bg-[#059669]/10 text-[#059669] border border-[#059669]/20 font-medium px-1.5 py-0.5 rounded text-[10px] flex items-center space-x-0.5">
                                    <svg class="w-2.5 h-2.5 fill-current" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Verified</span>
                                </span>
                            @endif
                        </div>

                        <span class="text-slate-400 font-regular">
                            {{ $listing->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $listings->links() }}
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white border border-slate-200 rounded-lg p-12 text-center my-8 shadow-sm">
            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <h3 class="text-base font-semibold text-[#0F172A]">No listings found</h3>
            <p class="text-sm font-regular text-slate-500 mt-1 max-w-sm mx-auto">
                Try adjusting your search criteria or category filter to discover campus items.
            </p>
        </div>
    @endif
</div>
