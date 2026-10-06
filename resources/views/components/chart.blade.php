@props([
    'data' => [],
    'valueLabel' => 'Value',
    'labelHeading' => 'Label',
    'prefix' => '',
    'suffix' => '',
    'empty' => 'Not enough data yet.',
])

@php
    $max = collect($data)->max('value') ?: 1;
    $summary = collect($data)->map(fn ($row) => "{$row['label']}: {$prefix}{$row['value']}{$suffix}")->implode(', ');
@endphp

<div {{ $attributes }}>
    @if (empty($data))
        <x-empty-state icon="info" :title="$empty" />
    @else
        <div role="img" aria-label="{{ $valueLabel }} by {{ $labelHeading }}: {{ $summary }}" class="space-y-2.5">
            @foreach ($data as $row)
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-24 flex-shrink-0 truncate text-slate-600 sm:w-32">{{ $row['label'] }}</span>
                    <span class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-300">
                        <span class="block h-full rounded-full bg-brand-600" style="width: {{ $max > 0 ? min(100, round(($row['value'] / $max) * 100, 1)) : 0 }}%"></span>
                    </span>
                    <span class="w-20 flex-shrink-0 text-right font-semibold text-ink">{{ $prefix }}{{ $row['value'] }}{{ $suffix }}</span>
                </div>
            @endforeach
        </div>

        <details class="mt-3">
            <summary class="cursor-pointer text-xs font-medium text-brand-800 underline underline-offset-2 dark:text-brand-300">
                View as table
            </summary>
            <table class="mt-2 w-full text-sm">
                <caption class="sr-only">{{ $valueLabel }} by {{ $labelHeading }}</caption>
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                        <th scope="col" class="py-1 font-medium">{{ $labelHeading }}</th>
                        <th scope="col" class="py-1 text-right font-medium">{{ $valueLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $row)
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5">{{ $row['label'] }}</td>
                            <td class="py-1.5 text-right font-medium text-ink">{{ $prefix }}{{ $row['value'] }}{{ $suffix }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
