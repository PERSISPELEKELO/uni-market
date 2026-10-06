@props(['title', 'description' => null])

<div {{ $attributes->class(['mb-6 flex flex-wrap items-center justify-between gap-3']) }}>
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
