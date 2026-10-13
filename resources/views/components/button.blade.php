@props([
    'variant' => 'primary',
    'size' => null,
    'icon' => null,
    'href' => null,
    'type' => 'button',
])

@php
    $classes = ['btn', "btn-{$variant}"];
    if ($size === 'sm') {
        $classes[] = 'btn-sm';
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-app-icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-app-icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
    </button>
@endif
