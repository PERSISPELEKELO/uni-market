@php
    $images = $listing->image_urls;
    $currentImage = $images[$activeImageIndex] ?? ($images[0] ?? null);
    $isOwner = $listing->isOwnedBy(auth()->user());
@endphp

<div>
    <a href="{{ route('listings.index') }}" class="mb-4 inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand-800">
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
                    <span class="text-3xl font-bold text-accent-700">K{{ number_format($listing->price, 2) }}</span>
                    <span class="badge badge-neutral">Condition: {{ $listing->condition_label }}</span>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <h2 class="text-sm font-semibold text-slate-800">About this item</h2>
                    <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-800">{{ $listing->description }}</p>
                </div>
            </div>

            <div class="card space-y-4 p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-slate-800">Seller</h2>

                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-brand-700 text-base font-semibold text-white" aria-hidden="true">
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($listing->seller->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $listing->seller->name }}</p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-600">
                            @if ($listing->seller->is_verified)
                                <span class="badge badge-success"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Official Student</span>
                            @endif
                            <span>Member since {{ $listing->seller->created_at->format('M Y') }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 border-t border-slate-100 pt-4">
                    @if ($isOwner)
                        <x-alert type="info">This is your listing.</x-alert>
                        <div class="flex flex-wrap gap-2">
                            @can('update', $listing)
                                <a href="{{ route('listings.edit', $listing) }}" class="btn btn-secondary flex-1"><x-app-icon name="pencil" class="h-4 w-4" /> Edit listing</a>
                            @endcan
                            <a href="{{ route('listings.mine') }}" class="btn btn-secondary flex-1">My listings</a>
                        </div>
                    @else
                        @if ($listing->status === 'active')
                            <button type="button" wire:click="initiatePurchase" wire:loading.attr="disabled" wire:target="initiatePurchase" class="btn btn-primary btn-block">
                                <x-app-icon name="bag" class="h-5 w-5" />
                                <span wire:loading.remove wire:target="initiatePurchase">{{ auth()->check() ? 'Reserve this item' : 'Log in to reserve this item' }}</span>
                                <span wire:loading wire:target="initiatePurchase">Reserving...</span>
                            </button>
                        @else
                            <button type="button" disabled class="btn btn-secondary btn-block">
                                {{ $listing->status === 'sold' ? 'This item has been sold' : 'This item is not available right now' }}
                            </button>
                        @endif

                        <button type="button" wire:click="contactSeller" class="btn btn-secondary btn-block">
                            <x-app-icon name="chat" class="h-5 w-5" /> {{ auth()->check() ? 'Message seller' : 'Log in to message seller' }}
                        </button>
                    @endif
                </div>
            </div>

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
