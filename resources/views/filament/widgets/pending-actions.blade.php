<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Pending Actions
        </x-slot>

        @php($items = $this->getItems())

        @if (empty($items))
            <div class="flex items-center gap-2 py-4 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-500" />
                Nothing needs your attention right now.
            </div>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($items as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="flex items-center gap-2">
                            <span
                                @class([
                                    'flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-sm font-semibold',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400' => $item['urgent'],
                                    'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400' => ! $item['urgent'],
                                ])
                            >
                                {{ $item['count'] }}
                            </span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                        </div>

                        <x-filament::button :href="$item['url']" tag="a" size="sm" color="gray">
                            Review
                        </x-filament::button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
