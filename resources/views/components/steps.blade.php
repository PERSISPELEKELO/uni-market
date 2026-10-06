@props(['items' => []])

<ol {{ $attributes->class(['space-y-4']) }}>
    @foreach ($items as $index => $item)
        <li class="flex gap-3">
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-700 text-sm font-bold text-white" aria-hidden="true">
                {{ $index + 1 }}
            </span>
            <div class="pt-0.5">
                <p class="font-semibold text-ink">{{ $item['title'] }}</p>
                @if (! empty($item['description']))
                    <p class="mt-0.5 text-sm text-slate-600">{{ $item['description'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
