@props([
    'label',
    'value' => null,
    'hint' => null,
    'trend' => null,
    'icon' => null,
    'tip' => null,
    // Numeric mode: animates 0 -> number on first paint (see resources/js/app.js's
    // countUp()). Only ever re-animates on first paint, never on a later
    // Livewire re-render, since Alpine's x-init does not re-run on every morph.
    'number' => null,
    'prefix' => '',
    'suffix' => '',
    'decimals' => 0,
])

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

    @if ($number !== null)
        <p
            class="mt-1 text-2xl font-bold text-ink"
            x-data="countUp({{ (float) $number }}, {{ (int) $decimals }}, @js($prefix), @js($suffix))"
            x-text="displayValue"
        >{{ $prefix }}{{ number_format((float) $number, $decimals) }}{{ $suffix }}</p>
    @else
        <p class="mt-1 text-2xl font-bold text-ink">{{ $value }}</p>
    @endif

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
