@php
    $images = $listing->image_urls;
    $currentImage = $images[$activeImageIndex] ?? ($images[0] ?? null);
    $isOwner = $listing->isOwnedBy(auth()->user());
@endphp

<div>
    <a href="{{ route('listings.index') }}" class="mb-4 inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand-800 dark:hover:text-brand-300">
        <x-app-icon name="arrow-left" class="h-4 w-4" /> Back to marketplace
    </a>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-8">

        <div class="space-y-3 lg:col-span-7">
            <x-listing-image :src="$currentImage" :alt="$listing->title" class="card aspect-[4/3] w-full">
                @if ($listing->status !== 'active')
                    <span class="absolute right-3 top-3"><x-status-badge :status="$listing->status" class="bg-white shadow-sm" /></span>
                @endif
            </x-listing-image>

            @if (count($images) > 1)
                <ul class="flex gap-3 overflow-x-auto pb-1" aria-label="Photo thumbnails">
                    @foreach ($images as $index => $imageUrl)
                        <li class="flex-shrink-0">
                            <button
                                type="button"
                                wire:click="setActiveImage({{ $index }})"
                                aria-label="Show photo {{ $index + 1 }} of {{ count($images) }}"
                                aria-current="{{ $activeImageIndex === $index ? 'true' : 'false' }}"
                                @class(['block h-16 w-16 overflow-hidden rounded-lg border-2 sm:h-20 sm:w-20', 'border-brand-700 ring-2 ring-brand-200' => $activeImageIndex === $index, 'border-slate-200 opacity-80 hover:opacity-100' => $activeImageIndex !== $index])
                            >
                                <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="space-y-5 lg:col-span-5">

            <div class="card space-y-4 p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-brand">{{ $listing->category->name }}</span>
                    <x-status-badge :status="$listing->status" />
                    <span class="text-xs text-slate-600">Listed {{ $listing->created_at->diffForHumans() }}</span>
                </div>

                <h1 class="break-words text-2xl font-bold leading-snug tracking-tight text-ink">{{ $listing->title }}</h1>

                <div class="flex flex-wrap items-baseline gap-3">
                    <span class="text-3xl font-bold text-ink">K{{ number_format($listing->price, 2) }}</span>
                    <span class="badge badge-neutral">Condition: {{ $listing->condition_label }}</span>
                    @if ($reservationCount > 0)
                        <span class="badge badge-info">
                            <x-app-icon name="user" class="h-3.5 w-3.5" />
                            {{ $reservationCount }} {{ \Illuminate\Support\Str::plural('reservation', $reservationCount) }}
                        </span>
                    @endif
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <h2 class="text-sm font-semibold text-slate-800">About this item</h2>
                    <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-800">{{ $listing->description }}</p>
                </div>
            </div>

            <div class="card space-y-4 p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-slate-800">Seller</h2>

                <a href="{{ route('profiles.show', $listing->seller) }}" class="flex items-center gap-3 rounded-lg hover:bg-slate-50">
                    <x-avatar :user="$listing->seller" class="h-11 w-11 flex-shrink-0 text-base" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $listing->seller->name }}</p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-600">
                            @if ($listing->seller->is_verified)
                                <span class="badge badge-success"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Official Student</span>
                            @endif
                            <span>Member since {{ $listing->seller->created_at->format('M Y') }}</span>
                        </div>
                    </div>
                </a>

                <div class="space-y-3 border-t border-slate-100 pt-4">
                    @if ($isOwner)
                        <x-alert type="info">This is your listing.</x-alert>
                        <div class="flex flex-wrap gap-2">
                            @can('update', $listing)
                                <a href="{{ route('listings.edit', $listing) }}" class="btn btn-secondary flex-1"><x-app-icon name="pencil" class="h-4 w-4" /> Edit listing</a>
                            @endcan
                            <a href="{{ route('listings.mine') }}" class="btn btn-secondary flex-1">My listings</a>
                        </div>
                    @elseif ($listing->status === 'active')
                        @if ($hasReserved)
                            <x-alert type="success">You've reserved this item. The seller will reach out if they choose you.</x-alert>
                        @else
                            <button type="button" wire:click="reserve" wire:loading.attr="disabled" wire:target="reserve" class="btn btn-primary btn-block">
                                <x-app-icon name="bag" class="h-5 w-5" />
                                <span wire:loading.remove wire:target="reserve">{{ auth()->check() ? 'Reserve this item' : 'Log in to reserve this item' }}</span>
                                <span wire:loading wire:target="reserve">Reserving...</span>
                            </button>
                        @endif

                        <button type="button" wire:click="contactSeller" class="btn btn-secondary btn-block">
                            <x-app-icon name="chat" class="h-5 w-5" /> {{ auth()->check() ? 'Message seller' : 'Log in to message seller' }}
                        </button>
                    @else
                        <button type="button" disabled class="btn btn-secondary btn-block">
                            {{ $listing->status === 'sold' ? 'This item has been sold' : 'This item is not available right now' }}
                        </button>
                    @endif
                </div>
            </div>

            @if ($isOwner && $activeReservations->isNotEmpty())
                <div class="card space-y-4 p-5 sm:p-6" aria-labelledby="reservations-heading">
                    <div class="flex items-center justify-between">
                        <h2 id="reservations-heading" class="text-sm font-semibold text-slate-800">
                            Interested buyers ({{ $activeReservations->count() }})
                        </h2>
                    </div>
                    <p class="text-xs text-slate-600">Choose who to sell to. Everyone else's reservation will be cancelled automatically.</p>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($activeReservations as $reservation)
                            <li wire:key="reservation-{{ $reservation->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <a href="{{ route('profiles.show', $reservation->buyer) }}" class="flex min-w-0 items-center gap-2.5 hover:opacity-80">
                                    <x-avatar :user="$reservation->buyer" class="h-9 w-9 flex-shrink-0 text-xs" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">
                                            {{ $reservation->buyer->name }}
                                            @if ($reservation->buyer->is_verified)
                                                <x-app-icon name="check-circle" class="inline h-3.5 w-3.5 text-accent-700" />
                                            @endif
                                        </p>
                                        <p class="text-xs text-slate-600">Reserved {{ $reservation->created_at->diffForHumans() }}</p>
                                    </div>
                                </a>
                                <div class="flex flex-shrink-0 items-center gap-2">
                                    <a href="{{ route('chat.thread', ['receiver' => $reservation->buyer_id, 'listing' => $listing->id]) }}" class="btn btn-secondary btn-sm">
                                        <x-app-icon name="chat" class="h-4 w-4" /> <span class="sr-only sm:not-sr-only">Message</span>
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="selectBuyer({{ $reservation->id }})"
                                        wire:confirm="Sell this item to {{ $reservation->buyer->name }}? Every other reservation will be cancelled."
                                        class="btn btn-primary btn-sm"
                                    >
                                        Select
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex items-start gap-3 rounded-xl border border-accent-200 bg-accent-50 p-4 text-sm">
                <x-app-icon name="shield" class="mt-0.5 h-5 w-5 flex-shrink-0 text-accent-700" />
                <div>
                    <p class="font-semibold text-accent-800">Protected campus handover</p>
                    <p class="mt-0.5 text-slate-700">
                        Reserve the item, meet on campus and share your handover code only once you have the item in hand.
                        You then get a 48-hour inspection window to confirm or dispute.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
