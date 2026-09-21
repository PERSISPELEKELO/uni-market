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
        'EXPIRED' => ['badge-neutral', 'Expired'],
        'SUSPENDED' => ['badge-danger', 'Suspended'],
        default => ['badge-neutral', ucfirst(strtolower(str_replace('_', ' ', $key)))],
    };
@endphp

<span {{ $attributes->class(['badge', $style]) }}>{{ $label }}</span>
