@props(['src' => null, 'alt' => '', 'lazy' => true, 'label' => true, 'zoom' => false, 'fit' => 'cover'])

<div {{ $attributes->class(['relative overflow-hidden bg-slate-100 dark:bg-slate-950']) }}>
    @if ($src && $fit === 'contain')
        {{-- Facebook-style frame: the whole photo stays visible
             (object-contain, never cropped or stretched), and a blurred,
             darkened enlargement of the same photo fills the empty space
             around it instead of leaving bare background - works the same
             in light and dark mode since the backdrop is always a dark
             photo blur, not a theme colour. --}}
        <div class="photo-frame absolute inset-0">
            <div class="absolute inset-0 scale-110 bg-cover bg-center blur-2xl brightness-50" style="background-image: url('{{ $src }}')" aria-hidden="true"></div>
            <img
                src="{{ $src }}"
                alt="{{ $alt }}"
                @if ($lazy) loading="lazy" @endif
                @class(['relative h-full w-full object-contain', 'transition-transform duration-200 group-hover:scale-105' => $zoom])
                onerror="this.closest('.photo-frame').classList.add('hidden'); this.closest('.photo-frame').nextElementSibling.classList.remove('hidden');"
            />
        </div>
    @elseif ($src)
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            @if ($lazy) loading="lazy" @endif
            @class(['h-full w-full object-cover', 'transition-transform duration-200 group-hover:scale-105' => $zoom])
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
