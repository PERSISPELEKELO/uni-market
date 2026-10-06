@props(['items' => []])

<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'mb-4']) }}>
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-slate-600">
        @foreach ($items as $index => $item)
            <li class="flex items-center gap-1.5">
                @if ($index > 0)
                    <x-app-icon name="chevron-right" class="h-3.5 w-3.5 text-slate-400" />
                @endif

                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" class="font-medium hover:text-brand-800 dark:hover:text-brand-300">{{ $item['label'] }}</a>
                @else
                    <span class="font-semibold text-ink" @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
