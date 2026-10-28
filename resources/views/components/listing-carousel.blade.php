@props(['title', 'items', 'keyPrefix', 'recSection' => null, 'description' => null])

@if ($items->isNotEmpty())
    <section class="mt-6 sm:mt-8" aria-labelledby="{{ $keyPrefix }}-heading">
        <h2 id="{{ $keyPrefix }}-heading" class="mb-0.5 text-base font-bold text-ink">{{ $title }}</h2>
        @if ($description)
            <p class="mb-3 text-xs text-slate-500">{{ $description }}</p>
        @else
            <div class="mb-3"></div>
        @endif
        <div class="no-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
            @foreach ($items as $listing)
                <x-listing-card :listing="$listing" :recSection="$recSection" wire:key="{{ $keyPrefix }}-{{ $listing->id }}" class="w-64 flex-shrink-0 sm:w-72" />
            @endforeach
        </div>
    </section>
@endif
