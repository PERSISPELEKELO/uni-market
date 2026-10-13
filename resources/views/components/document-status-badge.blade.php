@props(['status'])

@php
    [$style, $label, $icon] = match ($status) {
        \App\Models\StudentVerificationDocument::STATUS_APPROVED => ['badge-success', 'Approved', 'check-circle'],
        \App\Models\StudentVerificationDocument::STATUS_REJECTED => ['badge-danger', 'Rejected', 'warning'],
        default => ['badge-info', 'Awaiting review', 'clock'],
    };
@endphp

<span {{ $attributes->class(['badge', $style]) }}><x-app-icon :name="$icon" class="h-3 w-3" />{{ $label }}</span>
