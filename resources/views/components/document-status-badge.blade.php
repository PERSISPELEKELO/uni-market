@props(['status'])

@php
    [$style, $label] = match ($status) {
        \App\Models\StudentVerificationDocument::STATUS_APPROVED => ['badge-success', 'Approved'],
        \App\Models\StudentVerificationDocument::STATUS_REJECTED => ['badge-danger', 'Rejected'],
        default => ['badge-info', 'Awaiting review'],
    };
@endphp

<span {{ $attributes->class(['badge', $style]) }}>{{ $label }}</span>
