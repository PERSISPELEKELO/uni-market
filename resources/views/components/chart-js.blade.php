@props([
    'id',
    'type' => 'bar',
    'data' => ['labels' => [], 'datasets' => []],
    'options' => [],
    'clickDimension' => null,
    'empty' => 'Not enough data yet.',
    'height' => 'h-64',
    'ariaLabel' => 'Chart',
])

@php
    $hasData = collect($data['datasets'] ?? [])->flatMap(fn ($set) => $set['data'] ?? [])->filter()->isNotEmpty();
@endphp

<div {{ $attributes }}>
    @if (! $hasData)
        <x-empty-state icon="info" :title="$empty" />
    @else
        <div wire:ignore class="relative {{ $height }}">
            <canvas
                x-data="chartTile({
                    chartId: @js($id),
                    type: @js($type),
                    data: @js($data),
                    options: @js($options),
                    clickDimension: @js($clickDimension),
                })"
                x-ref="canvas"
                aria-label="{{ $ariaLabel }}"
                role="img"
            ></canvas>
        </div>

        <details class="mt-3">
            <summary class="cursor-pointer text-xs font-medium text-brand-800 underline underline-offset-2 dark:text-brand-300">
                View as table
            </summary>
            <table class="mt-2 w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                        <th class="py-1 font-medium">Label</th>
                        @foreach ($data['datasets'] as $dataset)
                            <th class="py-1 text-right font-medium">{{ $dataset['label'] ?? 'Value' }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['labels'] as $i => $label)
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5">{{ $label }}</td>
                            @foreach ($data['datasets'] as $dataset)
                                <td class="py-1.5 text-right font-medium text-ink">{{ $dataset['data'][$i] ?? '-' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
