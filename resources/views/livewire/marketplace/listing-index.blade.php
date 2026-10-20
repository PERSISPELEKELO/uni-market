<div x-data="{ filtersOpen: false, categoriesOpen: false }">

    <form role="search" x-on:submit.prevent class="mb-4">
        <label for="search" class="sr-only">Search listings</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500" aria-hidden="true">
                <x-app-icon name="search" class="h-5 w-5" />
            </span>
            <input
                id="search"
                type="search"
                wire:model.live.debounce.300ms="search"
                maxlength="100"
                autocomplete="off"
                placeholder="Search textbooks, laptops, dorm gear..."
                class="form-input pl-11"
            />
        </div>
    </form>

    {{-- Mobile/tablet pill row - replaces the old category chip row and the
         separate Browse-all link. Desktop gets the sidebar instead (B3). --}}
    <div class="no-scrollbar -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 lg:hidden" role="group" aria-label="Quick actions">
        <a href="{{ route('listings.create') }}" class="chip min-h-11 flex-shrink-0">
            <x-app-icon name="plus" class="h-3.5 w-3.5" /> Sell
        </a>
        <button type="button" x-on:click="categoriesOpen = true" class="chip min-h-11 flex-shrink-0" aria-haspopup="true" x-bind:aria-expanded="categoriesOpen">
            <x-app-icon name="layout-grid" class="h-3.5 w-3.5" /> Categories
        </button>
        <button type="button" x-on:click="filtersOpen = true" class="chip min-h-11 flex-shrink-0" aria-haspopup="true" x-bind:aria-expanded="filtersOpen">
            <x-app-icon name="menu" class="h-3.5 w-3.5" /> Filters
            @if ($hasActiveFilters)
                <span class="h-1.5 w-1.5 rounded-full bg-brand-700" aria-hidden="true"></span>
            @endif
        </button>
        <a href="{{ route('activity.buying', ['tab' => 'saved']) }}" class="chip min-h-11 flex-shrink-0">
            <x-app-icon name="bookmark" class="h-3.5 w-3.5" /> Saved
        </a>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Desktop-only left sidebar (B3). Only Browse all/Saved are linked
             here from the account-wide nav - Notifications/Inbox/Buying/
             Selling/Insights already live one click away in the header's
             My Activity menu and bell/message icons, so repeating them here
             would just duplicate that, not add anything. --}}
        <aside class="hidden lg:block lg:w-64 lg:flex-shrink-0 lg:space-y-5">
            <a href="{{ route('listings.create') }}" class="btn btn-primary btn-block">
                @if ($canSell === false)
                    <x-app-icon name="shield" class="h-4 w-4" /> Get verified to sell
                @else
                    <x-app-icon name="plus" class="h-4 w-4" /> Create new listing
                @endif
            </a>

            <nav class="card space-y-1 p-2" aria-label="Browse">
                <button type="button" wire:click="selectCategory(null)" @class(['flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 text-sm font-medium', 'bg-brand-50 text-brand-800 dark:bg-brand-500/10' => is_null($selectedCategory), 'text-slate-700 hover:bg-slate-50' => ! is_null($selectedCategory)])>
                    <x-app-icon name="layout-grid" class="h-4 w-4" /> Browse all
                </button>
                <a href="{{ route('activity.buying', ['tab' => 'saved']) }}" class="flex min-h-11 items-center gap-2.5 rounded-lg px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <x-app-icon name="bookmark" class="h-4 w-4" /> Saved
                </a>
            </nav>

            <nav class="card space-y-1 p-2" aria-label="Categories">
                @foreach ($categories as $category)
                    <button type="button" wire:key="sidebar-category-{{ $category->id }}" wire:click="selectCategory({{ $category->id }})" @class(['flex min-h-11 w-full items-center justify-between gap-2.5 rounded-lg px-3 text-left text-sm font-medium', 'bg-brand-50 text-brand-800 dark:bg-brand-500/10' => $selectedCategory === $category->id, 'text-slate-700 hover:bg-slate-50' => $selectedCategory !== $category->id])>
                        <span class="flex items-center gap-2.5 truncate">
                            <span aria-hidden="true">{{ $category->emoji() }}</span>
                            <span class="truncate">{{ $category->name }}</span>
                        </span>
                        @if ($category->listings_count > 0)
                            <span class="badge badge-neutral flex-shrink-0 px-1.5 py-0 text-[10px]">{{ $category->listings_count }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>

            <div class="card p-4">
                <button type="button" x-on:click="filtersOpen = !filtersOpen" class="flex min-h-11 w-full items-center justify-between text-sm font-semibold text-ink">
                    Filters
                    <x-app-icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="filtersOpen ? 'rotate-180' : ''" />
                </button>
                <div x-show="filtersOpen" x-transition class="mt-4">
                    <x-marketplace.filter-fields id-prefix="sidebar-filter" :has-active-filters="$hasActiveFilters" />
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1 space-y-6 sm:space-y-8">
            {{-- "Today's picks" grid comes first, within the first
                 screenful, with curated carousels below it (B1) - not the
                 other way around. --}}
            <section aria-live="polite" aria-busy="false">
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

                <div wire:loading.delay class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5" role="status" aria-label="Loading listings">
                    @for ($i = 0; $i < 10; $i++)
                        <x-listing-card-skeleton />
                    @endfor
                </div>

                <div wire:loading.delay.remove>
                    @if ($listings->count() > 0)
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
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
                                    @if ($canSell === false)
                                        <a href="{{ route('verification.student.form') }}" class="btn btn-primary">Get verified to sell</a>
                                    @else
                                        <a href="{{ auth()->check() ? route('listings.create') : route('register') }}" class="btn btn-primary">
                                            {{ auth()->check() ? 'Sell an item' : 'Create an account to sell' }}
                                        </a>
                                    @endif
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

            @if ($hotDeals->isNotEmpty())
                <section aria-labelledby="hot-deals-heading">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 id="hot-deals-heading" class="text-base font-bold text-ink">🔥 Hot Deals</h2>
                        <button type="button" wire:click="toggleHotDeals" class="text-sm font-medium text-brand-800 hover:underline dark:text-brand-300">
                            See all
                        </button>
                    </div>
                    <div class="no-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                        @foreach ($hotDeals as $deal)
                            <x-listing-card :listing="$deal" wire:key="hot-deal-{{ $deal->id }}" class="w-44 flex-shrink-0 sm:w-56" />
                        @endforeach
                    </div>
                </section>
            @endif

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
        </div>
    </div>

    {{-- Mobile/tablet-only: Filters as a bottom sheet, never inline (B1). --}}
    <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Filters">
        <div class="absolute inset-0 bg-black/40" x-on:click="filtersOpen = false"></div>
        <div
            x-show="filtersOpen"
            x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-4 dark:bg-slate-200"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-bold text-ink">Filters</h2>
                <button type="button" x-on:click="filtersOpen = false" class="-m-2 p-2" aria-label="Close">
                    <x-app-icon name="x" class="h-5 w-5" />
                </button>
            </div>
            <x-marketplace.filter-fields id-prefix="sheet-filter" :has-active-filters="$hasActiveFilters" />
            <button type="button" x-on:click="filtersOpen = false" class="btn btn-primary btn-block mt-5">Show results</button>
        </div>
    </div>

    {{-- Mobile/tablet-only: all categories, as a bottom sheet. --}}
    <div x-show="categoriesOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="All categories">
        <div class="absolute inset-0 bg-black/40" x-on:click="categoriesOpen = false"></div>
        <div
            x-show="categoriesOpen"
            x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-4 dark:bg-slate-200"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-bold text-ink">All categories</h2>
                <button type="button" x-on:click="categoriesOpen = false" class="-m-2 p-2" aria-label="Close">
                    <x-app-icon name="x" class="h-5 w-5" />
                </button>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <button
                    type="button"
                    wire:click="selectCategory(null)"
                    x-on:click="categoriesOpen = false"
                    @class(['flex flex-col items-center gap-1 rounded-xl border p-3 text-center', 'border-brand-700 bg-brand-50 dark:bg-brand-500/10' => is_null($selectedCategory), 'border-slate-200' => ! is_null($selectedCategory)])
                >
                    <x-app-icon name="layout-grid" class="h-6 w-6 text-slate-500" />
                    <span class="text-xs font-medium text-ink">All</span>
                </button>
                @foreach ($categories as $category)
                    <button
                        type="button"
                        wire:key="sheet-category-{{ $category->id }}"
                        wire:click="selectCategory({{ $category->id }})"
                        x-on:click="categoriesOpen = false"
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
</div>
