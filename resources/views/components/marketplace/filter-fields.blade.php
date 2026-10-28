@props(['idPrefix' => 'filter', 'hasActiveFilters' => false])

<div class="space-y-5">
    <div>
        <label for="{{ $idPrefix }}-condition" class="form-label">Condition</label>
        <select id="{{ $idPrefix }}-condition" wire:model.live="conditionFilter" class="form-input">
            <option value="">Any condition</option>
            @foreach (\App\Models\Listing::CONDITIONS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <span class="form-label">Price range (K)</span>
        <div class="flex items-center gap-2">
            <label for="{{ $idPrefix }}-price-min" class="sr-only">Minimum price</label>
            <input id="{{ $idPrefix }}-price-min" type="number" inputmode="decimal" min="0" step="0.01" wire:model.live.debounce.500ms="priceMin" placeholder="Min" class="form-input" />
            <span class="text-slate-400">–</span>
            <label for="{{ $idPrefix }}-price-max" class="sr-only">Maximum price</label>
            <input id="{{ $idPrefix }}-price-max" type="number" inputmode="decimal" min="0" step="0.01" wire:model.live.debounce.500ms="priceMax" placeholder="Max" class="form-input" />
        </div>
    </div>

    <div>
        <label for="{{ $idPrefix }}-sort" class="form-label">Sort by</label>
        <select id="{{ $idPrefix }}-sort" wire:model.live="sortBy" class="form-input">
            <option value="latest">Newest first</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
        </select>
    </div>

    <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-slate-800">
        <input type="checkbox" wire:model.live="hotDealsOnly" class="h-5 w-5 rounded border-slate-400 text-brand-700 focus:ring-brand-700" />
        <span>🔥 Hot deals only</span>
    </label>

    @if ($hasActiveFilters)
        <button type="button" wire:click="clearFilters" class="btn btn-secondary btn-sm btn-block">
            <x-app-icon name="x" class="h-4 w-4" /> Clear filters
        </button>
    @endif
</div>
