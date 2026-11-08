@props(['status'])

@php
    [$style, $label] = match (strtoupper((string) $status)) {
        'HELD' => ['badge-info', 'Held in escrow'],
        'RELEASED' => ['badge-success', 'Released to seller'],
        'REFUNDED' => ['badge-warning', 'Refunded to buyer'],
        'FAILED' => ['badge-danger', 'Payment failed'],
        default => ['badge-neutral', 'Payment pending'],
    };
@endphp

<span {{ $attributes->class(['badge', $style]) }} title="Simulated for demonstration only - no real money is processed">
    <x-app-icon name="credit-card" class="h-3 w-3" /> {{ $label }} <span class="text-[10px] opacity-75">(simulated)</span>
</span>
