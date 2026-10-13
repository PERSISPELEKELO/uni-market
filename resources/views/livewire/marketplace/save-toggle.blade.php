<button
    type="button"
    wire:click="toggle"
    wire:loading.attr="disabled"
    @class([
        'inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full shadow-sm transition-colors',
        'bg-brand-700 text-white hover:bg-brand-800' => $saved,
        'bg-white/95 text-slate-700 hover:bg-white dark:bg-slate-200/95' => ! $saved,
    ])
    aria-pressed="{{ $saved ? 'true' : 'false' }}"
    aria-label="{{ $saved ? 'Remove from saved items' : 'Save this item' }}"
    title="{{ $saved ? 'Saved' : 'Save' }}"
>
    <x-app-icon :name="$saved ? 'bookmark-filled' : 'bookmark'" class="h-4 w-4" />
    <span class="sr-only">{{ $saved ? 'Saved' : 'Save' }}</span>
</button>
