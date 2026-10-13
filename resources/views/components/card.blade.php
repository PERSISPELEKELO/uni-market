@props(['padding' => 'p-5 sm:p-6'])

<div {{ $attributes->class(['card', $padding]) }}>
    {{ $slot }}
</div>
