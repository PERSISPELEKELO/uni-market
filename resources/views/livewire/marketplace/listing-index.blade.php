<div>
    <section class="rounded-2xl bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 p-6 text-center text-white shadow-sm sm:p-10" aria-labelledby="hero-heading">
        <div class="mx-auto max-w-2xl">
            @guest
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15" aria-hidden="true">
                    <x-app-icon name="bag" class="h-7 w-7" />
                </div>
            @endguest

            <h1 id="hero-heading" class="mt-4 text-2xl font-bold leading-tight tracking-tight sm:text-4xl">
                Buy and sell within your campus community
            </h1>
            <p class="mx-auto mt-2 max-w-xl text-sm text-brand-100 sm:text-base">
                Textbooks, laptops, dorm gear and more from verified students, with in-app chat and protected handovers.
            </p>

            <form role="search" x-on:submit.prevent class="mt-5 sm:mt-6">
                <label for="search" class="sr-only">Search listings</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                        <x-app-icon name="search" class="h-5 w-5" />
                    </span>
                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Search textbooks, laptops, dorm gear..."
                        class="form-input border-transparent pl-11 text-left shadow-sm"
                    />
                </div>
            </form>

            @guest
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('listings.index') }}" class="btn min-h-11 border border-white/30 bg-white/10 px-4 text-white hover:bg-white/20">
                        <x-app-icon name="search" class="h-4 w-4" /> Browse the marketplace
                    </a>
                    <a href="{{ route('register') }}" class="btn min-h-11 bg-white px-4 text-brand-800 hover:bg-brand-50">
                        <x-app-icon name="plus" class="h-4 w-4" /> Join UniMarket
                    </a>
                </div>

                {{-- Only shown once every figure is genuinely meaningful -
                     a page full of zeros looks unfinished, not trustworthy. --}}
                @if ($heroStats && $heroStats['students'] >= 10 && $heroStats['listings'] >= 10 && $heroStats['sales'] >= 10)
                    <dl class="mt-8 grid grid-cols-3 gap-4 border-t border-white/15 pt-6">
                        <div>
                            <dt class="text-2xl font-bold sm:text-3xl">{{ number_format($heroStats['students']) }}+</dt>
                            <dd class="mt-0.5 text-xs text-brand-100 sm:text-sm">Students</dd>
                        </div>
                        <div>
                            <dt class="text-2xl font-bold sm:text-3xl">{{ number_format($heroStats['listings']) }}+</dt>
                            <dd class="mt-0.5 text-xs text-brand-100 sm:text-sm">Active listings</dd>
                        </div>
                        <div>
                            <dt class="text-2xl font-bold sm:text-3xl">{{ number_format($heroStats['sales']) }}+</dt>
                            <dd class="mt-0.5 text-xs text-brand-100 sm:text-sm">Completed sales</dd>
                        </div>
                    </dl>
                @endif
            @endguest
        </div>
    </section>

    @if ($hotDeals->isNotEmpty())
        <section class="mt-6 sm:mt-8" aria-labelledby="hot-deals-heading">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="hot-deals-heading" class="text-base font-bold text-ink">🔥 Hot Deals</h2>
                <button type="button" wire:click="toggleHotDeals" class="text-sm font-medium text-brand-800 hover:underline dark:text-brand-300">
                    See all
                </button>
            </div>
            <div class="no-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                @foreach ($hotDeals as $deal)
                    <x-listing-card :listing="$deal" wire:key="hot-deal-{{ $deal->id }}" class="w-64 flex-shrink-0 sm:w-72" />
                @endforeach
            </div>
        </section>
    @endif

    <section
        class="mt-6 sm:mt-8"
        aria-label="Filter by category"
        x-data="{
            canScrollLeft: false,
            canScrollRight: false,
            check() {
                const el = this.$refs.chips;
                this.canScrollLeft = el.scrollLeft > 4;
                this.canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
            },
        }"
        x-init="$nextTick(() => check())"
    >
        <div class="relative">
            <button
                type="button"
                x-show="canScrollLeft"
                x-cloak
                x-on:click="$refs.chips.scrollBy({ left: -200, behavior: 'smooth' })"
                class="absolute -left-3 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm sm:flex dark:bg-slate-200"
                aria-label="Scroll categories left"
            >
                <x-app-icon name="chevron-left" class="h-4 w-4" />
            </button>
            <button
                type="button"
                x-show="canScrollRight"
                x-cloak
                x-on:click="$refs.chips.scrollBy({ left: 200, behavior: 'smooth' })"
                class="absolute -right-3 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm sm:flex dark:bg-slate-200"
                aria-label="Scroll categories right"
            >
                <x-app-icon name="chevron-right" class="h-4 w-4" />
            </button>

            <div x-ref="chips" x-on:scroll.debounce.100ms="check()" class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0" role="group" aria-label="Categories">
                <button type="button" wire:click="selectCategory(null)" @class(['chip flex-shrink-0', 'chip-active' => is_null($selectedCategory)]) aria-pressed="{{ is_null($selectedCategory) ? 'true' : 'false' }}">
                    All
                </button>
                @foreach ($topCategories as $category)
                    <button type="button" wire:key="category-{{ $category->id }}" wire:click="selectCategory({{ $category->id }})" @class(['chip flex-shrink-0', 'chip-active' => $selectedCategory === $category->id]) aria-pressed="{{ $selectedCategory === $category->id ? 'true' : 'false' }}">
                        <span aria-hidden="true">{{ $category->emoji() }}</span>
                        <span>{{ $category->name }}</span>
                        @if ($category->listings_count > 0)
                            <span class="badge badge-neutral px-1.5 py-0 text-[10px]">{{ $category->listings_count }}</span>
                        @endif
                    </button>
                @endforeach

                <div class="relative flex-shrink-0" x-data="{ moreOpen: false }">
                    <button type="button" x-on:click="moreOpen = true" class="chip" aria-haspopup="true" x-bind:aria-expanded="moreOpen">
                        More <x-app-icon name="chevron-down" class="h-3.5 w-3.5" />
                    </button>

                    {{-- Mobile: full bottom sheet --}}
                    <div x-show="moreOpen" x-cloak class="fixed inset-0 z-50 sm:hidden" role="dialog" aria-modal="true" aria-label="All categories">
                        <div class="absolute inset-0 bg-black/40" x-on:click="moreOpen = false"></div>
                        <div x-show="moreOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" class="absolute inset-x-0 bottom-0 max-h-[75vh] overflow-y-auto rounded-t-2xl bg-white p-4 dark:bg-slate-200">
                            <div class="mb-3 flex items-center justify-between">
                                <h2 class="text-base font-bold text-ink">All categories</h2>
                                <button type="button" x-on:click="moreOpen = false" class="-m-2 p-2" aria-label="Close">
                                    <x-app-icon name="x" class="h-5 w-5" />
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach ($categories as $category)
                                    <button
                                        type="button"
                                        wire:key="more-mobile-{{ $category->id }}"
                                        wire:click="selectCategory({{ $category->id }})"
                                        x-on:click="moreOpen = false"
                                        @class(['flex flex-col items-center gap-1 rounded-xl border p-3 text-center', 'border-brand-700 bg-brand-50 dark:bg-brand-500/10' => $selectedCategory === $category->id, 'border-slate-200' => $selectedCategory !== $category->id])
                                    >
                                        <span class="text-2xl" aria-hidden="true">{{ $category->emoji() }}</span>
                                        <span class="text-xs font-medium text-ink">{{ $category->name }}</span>
                                        @if ($category->listings_count > 0)
                                            <span class="text-[10px] text-slate-500">{{ $category->listings_count }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Desktop: anchored popover --}}
                    <div x-show="moreOpen" x-cloak x-on:click.outside="moreOpen = false" x-transition class="absolute left-0 top-full z-50 mt-2 hidden w-80 rounded-xl border border-slate-200 bg-white p-3 shadow-lg sm:block dark:bg-slate-200">
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($categories as $category)
                                <button
                                    type="button"
                                    wire:key="more-desktop-{{ $category->id }}"
                                    wire:click="selectCategory({{ $category->id }})"
                                    x-on:click="moreOpen = false"
                                    @class(['flex flex-col items-center gap-1 rounded-lg border p-2 text-center hover:bg-slate-50', 'border-brand-700 bg-brand-50 dark:bg-brand-500/10' => $selectedCategory === $category->id, 'border-slate-200' => $selectedCategory !== $category->id])
                                >
                                    <span class="text-xl" aria-hidden="true">{{ $category->emoji() }}</span>
                                    <span class="text-xs font-medium text-ink">{{ $category->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="mt-6 flex flex-col gap-6 sm:mt-8 lg:flex-row lg:items-start" x-data="{ filtersOpen: window.innerWidth >= 1024 }">
        <aside class="space-y-5 lg:w-64 lg:flex-shrink-0">
            {{-- Nav links (Browse/Inbox/Buying/Selling) removed - they now
                 live in the top bar (desktop) and bottom tab bar (mobile),
                 so this sidebar is filters-only, as the icon nav redesign
                 intends. --}}
            <button type="button" x-on:click="filtersOpen = !filtersOpen" class="btn btn-secondary btn-block justify-between lg:hidden" x-bind:aria-expanded="filtersOpen" aria-controls="marketplace-filters">
                <span class="inline-flex items-center gap-2"><x-app-icon name="menu" class="h-4 w-4" /> Filters</span>
                <x-app-icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="filtersOpen ? 'rotate-180' : ''" />
            </button>

            <div id="marketplace-filters" x-show="filtersOpen" x-cloak x-transition class="card mt-3 space-y-5 p-5 lg:mt-0">
                {{-- Category has its own chip row above - no duplicate control here. --}}
                <div>
                    <label for="filter-condition" class="form-label">Condition</label>
                    <select id="filter-condition" wire:model.live="conditionFilter" class="form-input">
                        <option value="">Any condition</option>
                        @foreach (\App\Models\Listing::CONDITIONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <span class="form-label">Price range (K)</span>
                    <div class="flex items-center gap-2">
                        <label for="filter-price-min" class="sr-only">Minimum price</label>
                        <input id="filter-price-min" type="number" inputmode="decimal" min="0" step="0.01" wire:model.live.debounce.500ms="priceMin" placeholder="Min" class="form-input" />
                        <span class="text-slate-400">–</span>
                        <label for="filter-price-max" class="sr-only">Maximum price</label>
                        <input id="filter-price-max" type="number" inputmode="decimal" min="0" step="0.01" wire:model.live.debounce.500ms="priceMax" placeholder="Max" class="form-input" />
                    </div>
                </div>

                <div>
                    <label for="filter-sort" class="form-label">Sort by</label>
                    <select id="filter-sort" wire:model.live="sortBy" class="form-input">
                        <option value="latest">Newest first</option>
                        <option value="price_asc">Price: low to high</option>
                        <option value="price_desc">Price: high to low</option>
                    </select>
                </div>

                <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-slate-800">
                    <input type="checkbox" wire:model.live="hotDealsOnly" class="h-5 w-5 rounded border-slate-400 text-brand-700 focus:ring-brand-700" />
                    <span>🔥 Hot deals only</span>
                </label>

                @if ($hasActiveFilters)
                    <button type="button" wire:click="clearFilters" class="btn btn-secondary btn-sm btn-block">
                        <x-app-icon name="x" class="h-4 w-4" /> Clear filters
                    </button>
                @endif
            </div>
        </aside>

        <div class="min-w-0 flex-1 space-y-6 sm:space-y-8">
    <x-listing-carousel title="Recommended for you" :items="$recommendedForYou" key-prefix="recommended" rec-section="recommended_for_you" description="Based on what you've been looking at recently." />

    @if ($becauseYouViewed)
        <x-listing-carousel
            title="Because you viewed &quot;{{ \Illuminate\Support\Str::limit($becauseYouViewed['listing']->title, 40) }}&quot;"
            :items="$becauseYouViewed['items']"
            key-prefix="because-you-viewed"
            rec-section="because_you_viewed"
        />
    @endif

    <x-listing-carousel title="Popular with students like you" :items="$popularWithYou" key-prefix="popular" />

    <x-listing-carousel title="Trending this week" :items="$trending" key-prefix="trending" rec-section="trending" description="Real views in the last 24 hours." />

    <x-listing-carousel title="Good deals" :items="$goodDeals" key-prefix="good-deals" rec-section="good_deals" description="Priced below the typical price for their category and condition." />

    <x-listing-carousel title="Just listed" :items="$justListed" key-prefix="just-listed" rec-section="just_listed" />

    <section class="mt-6" aria-live="polite" aria-busy="false">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-ink">{{ $hasActiveFilters ? 'Results' : "Today's picks" }}</h2>
                <p class="text-sm text-slate-700">
                    <span class="font-semibold text-ink">{{ number_format($listings->total()) }}</span>
                    {{ \Illuminate\Support\Str::plural('item', $listings->total()) }} available
                </p>
            </div>
        </div>

        @if ($hasActiveFilters)
            <div class="mb-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
                @if ($selectedCategory)
                    <span class="chip chip-active">
                        {{ $categories->firstWhere('id', $selectedCategory)?->name }}
                        <button type="button" wire:click="selectCategory(null)" aria-label="Remove category filter"><x-app-icon name="x" class="h-3.5 w-3.5" /></button>
                    </span>
                @endif
                @if ($conditionFilter !== '' && array_key_exists($conditionFilter, \App\Models\Listing::CONDITIONS))
                    <span class="chip chip-active">
                        {{ \App\Models\Listing::CONDITIONS[$conditionFilter] }}
                        <button type="button" wire:click="$set('conditionFilter', '')" aria-label="Remove condition filter"><x-app-icon name="x" class="h-3.5 w-3.5" /></button>
                    </span>
                @endif
                @if ($hotDealsOnly)
                    <span class="chip chip-active">
                        🔥 Hot deals
                        <button type="button" wire:click="toggleHotDeals" aria-label="Remove hot deals filter"><x-app-icon name="x" class="h-3.5 w-3.5" /></button>
                    </span>
                @endif
                @if ($priceMin !== '' || $priceMax !== '')
                    <span class="chip chip-active">
                        K{{ $priceMin !== '' ? $priceMin : '0' }}–K{{ $priceMax !== '' ? $priceMax : '∞' }}
                        <button type="button" wire:click="clearPriceRange" aria-label="Remove price filter"><x-app-icon name="x" class="h-3.5 w-3.5" /></button>
                    </span>
                @endif
                <button type="button" wire:click="clearFilters" class="text-sm font-medium text-brand-800 hover:underline dark:text-brand-300">
                    Clear all
                </button>
                @if ($search !== '' || $selectedCategory || ($conditionFilter !== '' && array_key_exists($conditionFilter, \App\Models\Listing::CONDITIONS)))
                    <button type="button" wire:click="saveSearch" class="chip">
                        <x-app-icon name="bell" class="h-3.5 w-3.5" /> Save this search
                    </button>
                @endif
            </div>
        @endif

        <div wire:loading.delay class="grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5" role="status" aria-label="Loading listings">
            @for ($i = 0; $i < 8; $i++)
                <x-listing-card-skeleton />
            @endfor
        </div>

        <div wire:loading.delay.remove>
            @if ($listings->count() > 0)
                <div class="grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                    @foreach ($listings as $listing)
                        <x-listing-card :listing="$listing" wire:key="listing-{{ $listing->id }}" />
                    @endforeach
                </div>

                <div class="mt-8 flex flex-col items-center gap-3">
                    @if ($listings->hasMorePages())
                        <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore" class="btn btn-secondary">
                            <span wire:loading.remove wire:target="loadMore">Load more</span>
                            <span wire:loading wire:target="loadMore">Loading...</span>
                        </button>
                    @endif
                    <p class="text-xs text-slate-500">Showing {{ $listings->count() }} of {{ $listings->total() }}</p>
                </div>
            @else
                <div class="card px-6 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                        <x-app-icon name="search" class="h-6 w-6" />
                    </div>
                    @if ($hasActiveFilters)
                        <h2 class="mt-3 text-base font-semibold text-ink">No listings match your search</h2>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">Try a different keyword, or remove some filters to see more items.</p>
                        <button type="button" wire:click="clearFilters" class="btn btn-primary mt-4">Clear all filters</button>
                    @else
                        <h2 class="mt-3 text-base font-semibold text-ink">Nothing for sale yet</h2>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">Be the first to list an item on the campus marketplace.</p>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                            <a href="{{ auth()->check() ? route('listings.create') : route('register') }}" class="btn btn-primary">
                                {{ auth()->check() ? 'Sell an item' : 'Create an account to sell' }}
                            </a>
                            <button type="button" wire:click="toggleNotifyNewListings" class="btn btn-secondary">
                                @if (auth()->user()?->notify_new_listings)
                                    <x-app-icon name="check-circle" class="h-4 w-4" /> You'll be notified
                                @else
                                    <x-app-icon name="bell" class="h-4 w-4" /> Get notified when items arrive
                                @endif
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>
        </div>
    </div>
</div>
