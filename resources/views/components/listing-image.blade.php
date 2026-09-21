@props(['src' => null, 'alt' => '', 'lazy' => true, 'label' => true])

<div {{ $attributes->class(['relative overflow-hidden bg-slate-100']) }}>
    @if ($src)
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            @if ($lazy) loading="lazy" @endif
            class="h-full w-full object-cover"
            onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"
        />
    @endif

    <div class="{{ $src ? 'hidden' : '' }} flex h-full w-full flex-col items-center justify-center gap-1 text-slate-500">
        <x-app-icon name="photo" @class(['h-10 w-10' => $label, 'h-5 w-5' => ! $label]) />
        @if ($label)
            <span class="text-xs font-medium">No photo</span>
        @endif
    </div>

    {{ $slot }}
</div>
