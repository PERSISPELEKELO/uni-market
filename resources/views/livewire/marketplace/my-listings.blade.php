@php
    $statusTiles = [
        'needs_attention' => ['label' => 'Needs attention', 'icon' => 'warning'],
        'active' => ['label' => 'Active & pending', 'icon' => 'bag'],
        'sold' => ['label' => 'Sold', 'icon' => 'check-circle'],
        'draft' => ['label' => 'Drafts', 'icon' => 'pencil'],
        'to_renew' => ['label' => 'To renew', 'icon' => 'clock'],
    ];
@endphp

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">My listings</h1>
            <p class="mt-1 text-sm text-slate-600">Manage the items you are selling.</p>
        </div>
        <a href="{{ route('listings.create') }}" class="btn btn-primary">
            <x-app-icon name="plus" class="h-4 w-4" /> Sell an item
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-8">
        <div class="space-y-6 lg:col-span-9">
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="Overview">
                <x-stat-card label="Chats to answer" :value="$chatsToAnswer" icon="chat" />
                <x-stat-card label="Seller rating" :value="$sellerRating !== null ? number_format($sellerRating, 1).' / 5.0' : 'No ratings yet'" icon="chart-bar" />
            </section>

            <section aria-label="Listing status">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($statusTiles as $key => $tile)
                        <button
                            type="button"
                            wire:click="setStatusFilter('{{ $key }}')"
                            @class([
                                'card card-hover flex flex-col items-start gap-2 p-4 text-left',
                                'ring-2 ring-brand-700' => $statusFilter === $key,
                            ])
                            aria-pressed="{{ $statusFilter === $key ? 'true' : 'false' }}"
                        >
                            <x-app-icon :name="$tile['icon']" class="h-5 w-5 text-slate-400" />
                            <span class="text-2xl font-bold text-ink">{{ $counts[$key] }}</span>
                            <span class="text-xs font-medium text-slate-600">{{ $tile['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </section>

            @if ($needsActionListing)
                <section class="card space-y-4 border-warn-200 bg-warn-50 p-4 sm:p-5" aria-labelledby="needs-action-heading">
                    <h2 id="needs-action-heading" class="flex items-center gap-1.5 text-sm font-semibold text-warn-800">
                        <x-app-icon name="warning" class="h-4 w-4" /> Needs your attention
                    </h2>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <x-listing-image :src="$needsActionListing->cover_image_url" :alt="$needsActionListing->title" class="h-20 w-28 flex-shrink-0 rounded-lg" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$needsActionListing->status" />
                            </div>
                            <h3 class="mt-1 truncate text-base font-semibold text-ink">{{ $needsActionListing->title }}</h3>
                            <p class="text-sm font-bold text-ink">K{{ number_format($needsActionListing->price, 2) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:flex-shrink-0" x-data>
                            @if ($needsActionListing->status === \App\Models\Listing::STATUS_ACTIVE)
                                <button type="button" wire:click="markSoldManually({{ $needsActionListing->id }})" wire:confirm="Mark this as sold? This is for a sale made outside UniMarket." class="btn btn-success btn-sm">
                                    <x-app-icon name="check-circle" class="h-4 w-4" /> Mark as sold
                                </button>
                                <button type="button" wire:click="renew({{ $needsActionListing->id }})" class="btn btn-secondary btn-sm">
                                    <x-app-icon name="clock" class="h-4 w-4" /> Renew
                                </button>
                            @endif
                            <button
                                type="button"
                                wire:click="shareListing({{ $needsActionListing->id }})"
                                x-on:click="navigator.share ? navigator.share({ title: @js($needsActionListing->title), url: @js(route('listings.show', $needsActionListing)) }).catch(() => {}) : (navigator.clipboard?.writeText(@js(route('listings.show', $needsActionListing))), $wire.dispatch('notify', { type: 'success', message: 'Link copied.' }))"
                                class="btn btn-secondary btn-sm"
                            >
                                <x-app-icon name="share" class="h-4 w-4" /> Share
                            </button>
                            <a href="{{ route('listings.edit', $needsActionListing) }}" class="btn btn-secondary btn-sm min-w-11 px-0" title="Edit listing" aria-label="Edit listing">
                                <x-app-icon name="pencil" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </section>
            @endif

            @if ($listings->count() > 0)
                <ul class="space-y-3">
                    @foreach ($listings as $listing)
                        <li wire:key="listing-{{ $listing->id }}" class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                            <a href="{{ route('listings.show', $listing) }}" class="block flex-shrink-0">
                                <x-listing-image :src="$listing->cover_image_url" :alt="$listing->title" class="h-40 w-full rounded-lg sm:h-20 sm:w-28" />
                            </a>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-status-badge :status="$listing->status" />
                                    <span class="text-xs text-slate-600">{{ $listing->category->name }}</span>
                                </div>
                                <h2 class="mt-1 break-words text-base font-semibold text-ink">
                                    <a href="{{ route('listings.show', $listing) }}" class="hover:text-brand-800 dark:hover:text-brand-300">{{ $listing->title }}</a>
                                </h2>
                                <p class="text-sm font-bold text-ink">K{{ number_format($listing->price, 2) }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2 sm:flex-shrink-0">
                                @if ($listing->status === \App\Models\Listing::STATUS_DRAFT)
                                    <button type="button" wire:click="publish({{ $listing->id }})" class="btn btn-primary btn-sm">
                                        <x-app-icon name="chevron-up" class="h-4 w-4" /> Publish
                                    </button>
                                @endif
                                @if ($listing->isDueForRenewal())
                                    <button type="button" wire:click="renew({{ $listing->id }})" class="btn btn-secondary btn-sm min-w-11 px-0" title="Renew listing" aria-label="Renew listing">
                                        <x-app-icon name="clock" class="h-4 w-4" />
                                    </button>
                                @endif
                                <a href="{{ route('listings.show', $listing) }}" class="btn btn-secondary btn-sm min-w-11 px-0" title="View listing" aria-label="View listing">
                                    <x-app-icon name="eye" class="h-4 w-4" />
                                </a>
                                @can('update', $listing)
                                    <a href="{{ route('listings.edit', $listing) }}" class="btn btn-secondary btn-sm min-w-11 px-0" title="Edit listing" aria-label="Edit listing">
                                        <x-app-icon name="pencil" class="h-4 w-4" />
                                    </a>
                                @endcan
                                @can('delete', $listing)
                                    <button
                                        type="button"
                                        wire:click="delete({{ $listing->id }})"
                                        wire:confirm="Remove this listing? Buyers will no longer be able to see it."
                                        class="btn btn-danger-outline btn-sm min-w-11 px-0"
                                        title="Remove listing"
                                        aria-label="Remove listing"
                                    >
                                        <x-app-icon name="trash" class="h-4 w-4" />
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6">
                    {{ $listings->links() }}
                </div>
            @else
                <div class="card px-6 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                        <x-app-icon name="bag" class="h-6 w-6" />
                    </div>
                    @if ($statusFilter !== '')
                        <h2 class="mt-3 text-base font-semibold text-ink">No listings in this view</h2>
                        <button type="button" wire:click="setStatusFilter('{{ $statusFilter }}')" class="btn btn-secondary mt-4">Clear filter</button>
                    @else
                        <h2 class="mt-3 text-base font-semibold text-ink">You have not listed anything yet</h2>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">Got a textbook, gadget or dorm item you no longer need? List it in a couple of minutes.</p>
                        <a href="{{ route('listings.create') }}" class="btn btn-primary mt-4">Sell your first item</a>
                    @endif
                </div>
            @endif
        </div>

        <aside class="lg:col-span-3">
            <x-app-sidebar :groups="[
                ['label' => 'Seller', 'items' => [
                    ['label' => 'Seller dashboard', 'icon' => 'layout-grid', 'href' => route('listings.mine'), 'active' => true],
                    ['label' => 'Insights', 'icon' => 'chart-bar', 'href' => route('activity.index')],
                    ['label' => 'Marketplace profile', 'icon' => 'user', 'href' => route('profiles.show', auth()->user())],
                ]],
            ]" class="card p-3" />
        </aside>
    </div>
</div>
