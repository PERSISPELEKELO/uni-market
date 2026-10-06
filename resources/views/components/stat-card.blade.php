@props(['label', 'value', 'hint' => null, 'trend' => null, 'icon' => null, 'tip' => null])

<div {{ $attributes->class(['card p-4 sm:p-5']) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="flex items-center gap-1 text-xs font-medium text-slate-600">
            {{ $label }}
            @if ($tip)
                <x-info-tip :text="$tip" />
            @endif
        </p>
        @if ($icon)
            <x-app-icon :name="$icon" class="h-4 w-4 text-slate-400" />
        @endif
    </div>

    <p class="mt-1 text-2xl font-bold text-ink">{{ $value }}</p>

    @if ($trend !== null)
        <p @class([
            'mt-1 inline-flex items-center gap-1 text-xs font-semibold',
            'text-accent-700 dark:text-accent-300' => $trend > 0,
            'text-danger-700 dark:text-danger-400' => $trend < 0,
            'text-slate-500' => $trend == 0,
        ])>
            @if ($trend > 0)
                <x-app-icon name="chevron-up" class="h-3 w-3" /> Up {{ abs($trend) }}%
            @elseif ($trend < 0)
                <x-app-icon name="chevron-down" class="h-3 w-3" /> Down {{ abs($trend) }}%
            @else
                No change
            @endif
            <span class="font-normal text-slate-500">vs previous period</span>
        </p>
    @endif

    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
