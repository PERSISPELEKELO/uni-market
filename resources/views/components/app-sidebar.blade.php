@props(['groups' => []])

{{--
    Generic, presentational section sidebar shared by the seller dashboard,
    buying page and marketplace nav rail. A group's items can be plain links
    (href) or Livewire actions (wireClick) - the caller's Livewire class
    builds the $groups array and decides which. Collapsing is pure Alpine,
    no Livewire round-trip.
--}}
<nav {{ $attributes->class(['space-y-4']) }} aria-label="Section navigation">
    @foreach ($groups as $group)
        <div
            @if ($group['collapsible'] ?? false)
                x-data="{ open: {{ ($group['defaultOpen'] ?? true) ? 'true' : 'false' }} }"
            @endif
        >
            @if (! empty($group['label']))
                @if ($group['collapsible'] ?? false)
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="flex min-h-9 w-full items-center justify-between rounded-lg px-2 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:text-slate-700"
                        x-bind:aria-expanded="open"
                    >
                        <span>{{ $group['label'] }}</span>
                        <x-app-icon name="chevron-down" class="h-3.5 w-3.5 transition-transform" x-bind:class="{ '-rotate-90': ! open }" />
                    </button>
                @else
                    <p class="min-h-9 px-2 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group['label'] }}</p>
                @endif
            @endif

            <ul @if ($group['collapsible'] ?? false) x-cloak x-show="open" @endif class="space-y-0.5">
                @foreach ($group['items'] ?? [] as $item)
                    @php $active = $item['active'] ?? false; @endphp
                    <li>
                        @if (isset($item['wireClick']))
                            <button
                                type="button"
                                wire:click="{{ $item['wireClick'] }}"
                                @class([
                                    'flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                    'bg-brand-50 text-brand-800 dark:bg-brand-500/10' => $active,
                                    'text-slate-700 hover:bg-slate-100' => ! $active,
                                ])
                                @if ($active) aria-current="true" @endif
                            >
                                @if (! empty($item['icon']))
                                    <x-app-icon :name="$item['icon']" class="h-5 w-5 flex-shrink-0" />
                                @endif
                                <span class="min-w-0 flex-1 truncate text-left">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="badge border-accent-700 bg-accent-700 text-white">{{ $item['badge'] }}</span>
                                @endif
                            </button>
                        @else
                            <a
                                href="{{ $item['href'] ?? '#' }}"
                                @class([
                                    'flex min-h-11 items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                    'bg-brand-50 text-brand-800 dark:bg-brand-500/10' => $active,
                                    'text-slate-700 hover:bg-slate-100' => ! $active,
                                ])
                                @if ($active) aria-current="page" @endif
                            >
                                @if (! empty($item['icon']))
                                    <x-app-icon :name="$item['icon']" class="h-5 w-5 flex-shrink-0" />
                                @endif
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="badge border-accent-700 bg-accent-700 text-white">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
