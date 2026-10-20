@props(['listing', 'recSection' => null])

@php
    // Tags the link so ListingShow can attribute a click back to whichever
    // recommendation carousel it came from (see RecommendationEvent) - a
    // plain browse-grid card passes no recSection and gets a plain link.
    $href = $recSection ? route('listings.show', ['listing' => $listing, 'rec' => $recSection]) : route('listings.show', $listing);
@endphp

@php
    // Facebook-style: at most one badge over the photo, most-newsworthy
    // first - a deal is more actionable than "just listed", and condition
    // belongs on the detail page, not cluttering the grid.
    $badge = $listing->isHotDeal() ? 'hot_deal' : ($listing->created_at->diffInHours() < 24 ? 'just_listed' : null);
@endphp

<article {{ $attributes->class(['card card-hover group flex flex-col overflow-hidden']) }}>
    <div class="relative">
        <a href="{{ $href }}" class="block focus-visible:outline-offset-[-2px]" aria-label="{{ $listing->title }}, K{{ number_format($listing->price, 2) }}">
            <x-listing-image :src="$listing->cover_thumbnail_url" :alt="$listing->title" class="aspect-square w-full" fit="contain" zoom>
                @if ($badge === 'hot_deal')
                    <span class="badge absolute left-3 top-3 border-none bg-danger-600 text-white shadow-sm">
                        🔥 Hot Deal
                    </span>
                @elseif ($badge === 'just_listed')
                    <span class="badge absolute left-3 top-3 border-none bg-accent-700 text-white shadow-sm">
                        Just listed
                    </span>
                @endif
            </x-listing-image>
        </a>

        @auth
            @unless ($listing->isOwnedBy(auth()->user()))
                <div class="absolute bottom-3 right-3">
                    @livewire('marketplace.save-toggle', ['listing' => $listing], key('save-'.$listing->id))
                </div>
            @endunless
        @endauth
    </div>

    <div class="flex flex-1 flex-col p-3 sm:p-4">
        @if ($listing->isHotDeal())
            <div class="flex flex-wrap items-baseline gap-2">
                <span class="text-lg font-bold text-danger-700 dark:text-danger-400">K{{ number_format($listing->price, 2) }}</span>
                <span class="text-xs text-slate-500 line-through">K{{ number_format($listing->previous_price, 2) }}</span>
            </div>
        @else
            <p class="text-lg font-bold text-ink">K{{ number_format($listing->price, 2) }}</p>
        @endif

        <h3 class="mt-0.5 text-sm font-medium text-ink">
            <a href="{{ $href }}" class="line-clamp-2 break-words hover:text-brand-800 dark:hover:text-brand-300">{{ $listing->title }}</a>
        </h3>

        <p class="mt-1.5 truncate text-xs text-slate-600">
            @if (($listing->active_reservations_count ?? 0) > 0)
                {{ $listing->active_reservations_count }} {{ \Illuminate\Support\Str::plural('reservation', $listing->active_reservations_count) }}
            @else
                @if ($listing->seller->is_verified)
                    <span class="inline-flex items-center gap-0.5 text-accent-700 dark:text-accent-400"><x-app-icon name="check-circle" class="h-3 w-3" /> Verified</span> ·
                @endif
                {{ $listing->created_at->diffForHumans(short: true) }}
            @endif
        </p>
    </div>
</article>
