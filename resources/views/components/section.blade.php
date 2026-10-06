@props(['title' => null, 'description' => null])

<section {{ $attributes->class(['space-y-4']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($title)
                    <h2 class="text-lg font-bold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-slate-600">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
