<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Buying</h1>
            <p class="mt-1 text-sm text-slate-600">What you've been looking at, saved and bought before.</p>
        </div>
        <a href="{{ route('listings.index') }}" class="btn btn-secondary">
            <x-app-icon name="search" class="h-4 w-4" /> Browse the marketplace
        </a>
    </div>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="Buying overview">
        <x-stat-card label="Items bought" :number="$buyerTotals['total_items']" icon="bag" />
        <x-stat-card label="Total spent" :number="$buyerTotals['total_spent']" prefix="K" :decimals="2" icon="upload" />
        <x-stat-card label="Average per item" :number="$buyerTotals['average_spend']" prefix="K" :decimals="2" icon="chart-bar" />
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-8">
        <aside class="lg:col-span-3">
            <x-app-sidebar :groups="[
                ['label' => 'Buying', 'items' => [
                    ['label' => 'Recent activity', 'icon' => 'clock', 'href' => '#recent-activity'],
                    ['label' => 'Saved', 'icon' => 'bookmark', 'href' => '#saved', 'badge' => $saved->count()],
                    ['label' => 'Your alerts', 'icon' => 'warning', 'href' => '#alerts', 'badge' => $alerts->count()],
                    ['label' => 'Following', 'icon' => 'users', 'href' => '#following'],
                    ['label' => 'Marketplace profile', 'icon' => 'user', 'href' => route('profiles.show', auth()->user())],
                ]],
            ]" class="card p-3" />
        </aside>

        <div class="space-y-6 lg:col-span-6">
            <section id="alerts" aria-labelledby="alerts-heading">
                @if ($alerts->isNotEmpty())
                    <div class="card space-y-4 border-accent-200 bg-accent-50 p-4 sm:p-5">
                        <h2 id="alerts-heading" class="flex items-center gap-1.5 text-sm font-semibold text-accent-800">
                            <x-app-icon name="warning" class="h-4 w-4" /> Price dropped on items you saved
                        </h2>
                        <ul class="divide-y divide-accent-100">
                            @foreach ($alerts as $alert)
                                <li class="flex items-center gap-3 py-2.5">
                                    <x-listing-image :src="$alert->listing->cover_image_url" :alt="$alert->listing->title" class="h-12 w-12 flex-shrink-0 rounded-lg" />
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('listings.show', $alert->listing) }}" class="block truncate text-sm font-medium text-ink hover:text-brand-800 dark:hover:text-brand-300">{{ $alert->listing->title }}</a>
                                        <p class="text-xs text-slate-600">
                                            <span class="text-slate-500 line-through">K{{ number_format($alert->price_at_save, 2) }}</span>
                                            <span class="font-semibold text-accent-700 dark:text-accent-300">K{{ number_format($alert->listing->price, 2) }}</span>
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section id="recent-activity" class="card space-y-4 p-4 sm:p-5" aria-labelledby="recent-activity-heading">
                <h2 id="recent-activity-heading" class="text-sm font-semibold text-ink">Recent activity</h2>
                @if ($recentActivity->isEmpty())
                    <x-empty-state icon="eye" title="Nothing viewed yet" description="Listings you open will show up here." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($recentActivity as $listing)
                            <li class="flex items-center gap-3 py-2.5">
                                <x-listing-image :src="$listing->cover_image_url" :alt="$listing->title" class="h-12 w-12 flex-shrink-0 rounded-lg" />
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('listings.show', $listing) }}" class="block truncate text-sm font-medium text-ink hover:text-brand-800 dark:hover:text-brand-300">{{ $listing->title }}</a>
                                    <p class="text-xs text-slate-600">{{ $listing->category->name }} &middot; K{{ number_format($listing->price, 2) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section id="saved" class="card space-y-4 p-4 sm:p-5" aria-labelledby="saved-heading">
                <h2 id="saved-heading" class="text-sm font-semibold text-ink">Saved</h2>
                @if ($saved->isEmpty())
                    <x-empty-state icon="bookmark" title="No saved listings" description="Tap the bookmark icon on any listing to save it here." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($saved as $item)
                            <li class="flex items-center gap-3 py-2.5">
                                <x-listing-image :src="$item->listing->cover_image_url" :alt="$item->listing->title" class="h-12 w-12 flex-shrink-0 rounded-lg" />
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('listings.show', $item->listing) }}" class="block truncate text-sm font-medium text-ink hover:text-brand-800 dark:hover:text-brand-300">{{ $item->listing->title }}</a>
                                    <p class="text-xs text-slate-600">{{ $item->listing->category->name }} &middot; K{{ number_format($item->listing->price, 2) }}</p>
                                </div>
                                @livewire('marketplace.save-toggle', ['listing' => $item->listing], key('save-'.$item->listing->id))
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section id="following" class="card space-y-4 p-4 sm:p-5" aria-labelledby="following-heading">
                <h2 id="following-heading" class="text-sm font-semibold text-ink">Following</h2>
                <p class="text-xs text-slate-500">Sellers you've bought from before.</p>
                @if ($following->isEmpty())
                    <x-empty-state icon="users" title="No past sellers yet" description="Complete a purchase and the seller will appear here." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($following as $seller)
                            <li class="flex items-center gap-3 py-2.5">
                                <x-avatar :user="$seller" class="h-9 w-9 flex-shrink-0 text-sm" />
                                <a href="{{ route('profiles.show', $seller) }}" class="min-w-0 flex-1 truncate text-sm font-medium text-ink hover:text-brand-800 dark:hover:text-brand-300">{{ $seller->name }}</a>
                                @if ($seller->is_verified)
                                    <span class="badge badge-success flex-shrink-0 px-1.5 py-0 text-[11px]"><x-app-icon name="check-circle" class="h-3 w-3" /> Verified</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="lg:col-span-3">
            <div class="card space-y-3 p-4 text-center">
                <x-avatar :user="auth()->user()" class="mx-auto h-16 w-16 text-xl" />
                <p class="font-semibold text-ink">{{ auth()->user()->name }}</p>
                <p class="text-sm text-slate-600">{{ $activeListingCount }} active {{ \Illuminate\Support\Str::plural('listing', $activeListingCount) }}</p>
                <div class="space-y-2">
                    <a href="{{ route('listings.create') }}" class="btn btn-primary btn-block btn-sm">
                        <x-app-icon name="plus" class="h-4 w-4" /> Create a listing
                    </a>
                    <a href="{{ route('profiles.show', auth()->user()) }}" class="btn btn-secondary btn-block btn-sm">View my profile</a>
                </div>
            </div>
        </aside>
    </div>
</div>
