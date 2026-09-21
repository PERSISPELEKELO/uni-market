@props(['type' => 'info'])

@php
    $icon = match ($type) {
        'success' => 'check-circle',
        'warning' => 'warning',
        'error' => 'error',
        default => 'info',
    };
@endphp

<div {{ $attributes->class(['alert', 'alert-' . $type]) }} role="{{ in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' }}">
    <x-app-icon :name="$icon" class="mt-0.5 h-5 w-5 flex-shrink-0" />
    <div class="min-w-0 flex-1 break-words">{{ $slot }}</div>
</div>
