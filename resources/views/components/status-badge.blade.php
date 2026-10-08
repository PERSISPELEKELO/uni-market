@props(['status'])

@php
    $key = strtoupper((string) $status);

    [$style, $label] = match ($key) {
        'ACTIVE' => ['badge-success', 'Available'],
        'PENDING' => ['badge-info', 'Reserved'],
        'INITIATED', 'RESERVED', 'PENDING_MEETING' => ['badge-info', 'Awaiting meet-up'],
        'ITEM_INSPECTION', 'HANDED_OVER' => ['badge-warning', 'Inspection'],
        'SOLD' => ['badge-success', 'Sold'],
        'COMPLETED' => ['badge-success', 'Completed'],
        'DISPUTED' => ['badge-warning', 'Disputed'],
        'RESOLVED_BUYER' => ['badge-success', "Resolved in the buyer's favour"],
        'RESOLVED_SELLER' => ['badge-success', "Resolved in the seller's favour"],
        'EXPIRED' => ['badge-neutral', 'Expired'],
        'SUSPENDED' => ['badge-danger', 'Suspended'],
        default => ['badge-neutral', ucfirst(strtolower(str_replace('_', ' ', $key)))],
    };

    $icon = match ($style) {
        'badge-success' => 'check-circle',
        'badge-warning', 'badge-danger' => 'warning',
        default => 'clock',
    };
@endphp

<span {{ $attributes->class(['badge', $style]) }}><x-app-icon :name="$icon" class="h-3 w-3" />{{ $label }}</span>
