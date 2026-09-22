@props(['listing'])

<article {{ $attributes->class(['card group flex flex-col overflow-hidden transition-shadow hover:shadow-md']) }}>
    <a href="{{ route('listings.show', $listing) }}" class="block focus-visible:outline-offset-[-2px]" aria-label="{{ $listing->title }}, K{{ number_format($listing->price, 2) }}">
        <x-listing-image :src="$listing->cover_image_url" :alt="$listing->title" class="aspect-[4/3] w-full">
            <span class="badge badge-neutral absolute left-3 top-3 bg-white/95 shadow-sm">
                {{ $listing->condition_label }}
            </span>
        </x-listing-image>
    </a>

    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $listing->category->name }}</p>

        <h3 class="mt-1 text-base font-semibold text-ink">
            <a href="{{ route('listings.show', $listing) }}" class="line-clamp-2 break-words hover:text-brand-800">{{ $listing->title }}</a>
        </h3>

        <p class="mt-3 text-lg font-bold text-accent-700">K{{ number_format($listing->price, 2) }}</p>

        @if (($listing->active_reservations_count ?? 0) > 0)
            <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-info-700">
                <x-app-icon name="user" class="h-3.5 w-3.5" />
                {{ $listing->active_reservations_count }} {{ \Illuminate\Support\Str::plural('reservation', $listing->active_reservations_count) }}
            </p>
        @endif
    </div>

    <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs sm:px-5">
        <div class="flex min-w-0 items-center gap-1.5">
            <span class="truncate font-medium text-slate-800">{{ $listing->seller->name }}</span>
            @if ($listing->seller->is_verified)
                <span class="badge badge-success flex-shrink-0 px-1.5 py-0 text-[11px]">
                    <x-app-icon name="check-circle" class="h-3 w-3" /> Verified
                </span>
            @endif
        </div>
        <time class="flex-shrink-0 text-slate-600" datetime="{{ $listing->created_at->toIso8601String() }}">{{ $listing->created_at->diffForHumans(short: true) }}</time>
    </div>
</article>
