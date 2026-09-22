@props(['user'])

@if ($user->avatar_url)
    <img
        src="{{ $user->avatar_url }}"
        alt="{{ $user->name }}"
        {{ $attributes->class(['flex-shrink-0 rounded-full object-cover']) }}
    />
@else
    <span {{ $attributes->class(['flex flex-shrink-0 items-center justify-center rounded-full bg-brand-700 font-semibold text-white']) }} aria-hidden="true">{{ $user->initial() }}</span>
@endif
