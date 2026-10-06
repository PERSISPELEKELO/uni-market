@props(['text', 'label' => 'More information'])

<span x-data="{ open: false }" x-on:keydown.escape="open = false" class="relative inline-flex" x-on:click.outside="open = false">
    <button
        type="button"
        x-on:click="open = !open"
        x-bind:aria-expanded="open"
        aria-label="{{ $label }}"
        class="inline-flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-slate-400 hover:text-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:hover:text-brand-300"
    >
        <x-app-icon name="info" class="h-4 w-4" />
    </button>

    <span
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        role="tooltip"
        class="absolute left-1/2 top-full z-20 mt-1.5 w-56 -translate-x-1/2 rounded-lg border border-slate-200 bg-white p-2.5 text-xs font-normal leading-relaxed text-slate-700 shadow-lg dark:bg-slate-200 dark:text-slate-800"
    >
        {{ $text }}
    </span>
</span>
