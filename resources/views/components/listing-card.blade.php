@props(['listing'])

<article {{ $attributes->class(['card group flex flex-col overflow-hidden transition-shadow hover:shadow-md']) }}>
    <a href="{{ route('listings.show', $listing) }}" class="block focus-visible:outline-offset-[-2px]" aria-label="{{ $listing->title }}, K{{ number_format($listing->price, 2) }}">
        <x-listing-image :src="$listing->cover_image_url" :alt="$listing->title" class="aspect-[4/3] w-full">
            <span class="badge badge-neutral absolute left-3 top-3 bg-white/95 shadow-sm dark:bg-slate-200/95">
                {{ $listing->condition_label }}
            </span>
            @if ($listing->isHotDeal())
                <span class="badge absolute right-3 top-3 border-none bg-danger-600 text-white shadow-sm">
                    🔥 Hot Deal
                </span>
            @endif
        </x-listing-image>
    </a>

    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $listing->category->name }}</p>

        <h3 class="mt-1 text-base font-semibold text-ink">
            <a href="{{ route('listings.show', $listing) }}" class="line-clamp-2 break-words hover:text-brand-800 dark:hover:text-brand-300">{{ $listing->title }}</a>
        </h3>

        @if ($listing->isHotDeal())
            <div class="mt-3 flex flex-wrap items-baseline gap-2">
                <span class="text-sm text-slate-500 line-through">K{{ number_format($listing->previous_price, 2) }}</span>
                <span class="text-lg font-bold text-danger-700 dark:text-danger-400">K{{ number_format($listing->price, 2) }}</span>
            </div>
            <p class="mt-0.5 text-xs font-semibold text-danger-700 dark:text-danger-400">
                Save K{{ number_format($listing->discountAmount(), 2) }}
                @if ($listing->discountPercentage() !== null)
                    ({{ $listing->discountPercentage() }}% off)
                @endif
            </p>
        @else
            <p class="mt-3 text-lg font-bold text-ink">K{{ number_format($listing->price, 2) }}</p>
        @endif

        @if (($listing->active_reservations_count ?? 0) > 0)
            <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-info-700 dark:text-info-400">
                <x-app-icon name="user" class="h-3.5 w-3.5" />
                {{ $listing->active_reservations_count }} {{ \Illuminate\Support\Str::plural('reservation', $listing->active_reservations_count) }}
            </p>
        @endif
    </div>

    <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs dark:bg-transparent sm:px-5">
        <div class="flex min-w-0 items-center gap-1.5">
            <x-avatar :user="$listing->seller" class="h-5 w-5 flex-shrink-0 text-[10px]" />
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
