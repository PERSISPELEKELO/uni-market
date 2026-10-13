<div class="border-t border-slate-100 px-3 py-2.5" role="group" aria-label="Display">
    <p class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <x-app-icon name="settings" class="h-3.5 w-3.5" /> Display
    </p>
    <div class="grid grid-cols-3 gap-1">
        @foreach (['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Automatic'] as $value => $label)
            <button
                type="button"
                wire:click="persist('{{ $value }}')"
                x-on:click="window.uniMarketTheme.set('{{ $value }}')"
                @class([
                    'rounded-lg px-2 py-1.5 text-xs font-medium transition-colors',
                    'bg-brand-700 text-white' => $current === $value,
                    'text-slate-700 hover:bg-slate-100' => $current !== $value,
                ])
                @if ($current === $value) aria-pressed="true" @endif
            >
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>
