<div>
    <section class="rounded-2xl bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 p-6 text-white shadow-sm sm:p-10" aria-labelledby="hero-heading">
        <div class="max-w-2xl">
            <h1 id="hero-heading" class="text-2xl font-bold leading-tight tracking-tight sm:text-4xl">
                Buy and sell within your campus community
            </h1>
            <p class="mt-2 text-sm text-brand-100 sm:text-base">
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
                        class="form-input border-transparent pl-11 shadow-sm"
                    />
                </div>
            </form>

            @guest
                <p class="mt-4 text-sm text-brand-100">
                    New here?
                    <a href="{{ route('register') }}" class="font-semibold text-white underline underline-offset-2 hover:text-brand-200">Create a free account</a>
                    to start selling.
                </p>
            @endguest
        </div>
    </section>

    <section class="mt-6 sm:mt-8" aria-label="Filter listings">
        <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Categories">
            <button type="button" wire:click="selectCategory(null)" @class(['chip', 'chip-active' => is_null($selectedCategory)]) aria-pressed="{{ is_null($selectedCategory) ? 'true' : 'false' }}">
                All items
            </button>
            @foreach ($categories as $category)
                <button type="button" wire:key="category-{{ $category->id }}" wire:click="selectCategory({{ $category->id }})" @class(['chip', 'chip-active' => $selectedCategory === $category->id]) aria-pressed="{{ $selectedCategory === $category->id ? 'true' : 'false' }}">
                    <span>{{ $category->name }}</span>
                    <span class="text-xs opacity-80">({{ $category->listings_count }})</span>
                </button>
            @endforeach
        </div>

        <div class="mt-4 flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Condition">
                <span class="mr-1 text-sm font-medium text-slate-700">Condition:</span>
                @foreach (\App\Models\Listing::CONDITIONS as $key => $label)
                    <button type="button" wire:key="condition-{{ $key }}" wire:click="setCondition('{{ $key }}')" @class(['chip min-h-9 px-3 py-1.5', 'chip-active' => $conditionFilter === $key]) aria-pressed="{{ $conditionFilter === $key ? 'true' : 'false' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <label for="sort" class="text-sm font-medium text-slate-700">Sort by</label>
                <select id="sort" wire:model.live="sortBy" class="form-input min-h-10 w-auto py-2">
                    <option value="latest">Newest first</option>
                    <option value="price_asc">Price: low to high</option>
                    <option value="price_desc">Price: high to low</option>
                </select>
            </div>
        </div>
    </section>

    <section class="mt-6" aria-live="polite" aria-busy="false">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-slate-700">
                <span class="font-semibold text-ink">{{ number_format($listings->total()) }}</span>
                {{ \Illuminate\Support\Str::plural('item', $listings->total()) }} available
            </p>
            @if ($hasActiveFilters)
                <button type="button" wire:click="clearFilters" class="btn btn-secondary btn-sm">
                    <x-app-icon name="x" class="h-4 w-4" /> Clear filters
                </button>
            @endif
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($listings->count() > 0)
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($listings as $listing)
                        <x-listing-card :listing="$listing" wire:key="listing-{{ $listing->id }}" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $listings->links() }}
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
                        <a href="{{ auth()->check() ? route('listings.create') : route('register') }}" class="btn btn-primary mt-4">
                            {{ auth()->check() ? 'Sell an item' : 'Create an account to sell' }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>
