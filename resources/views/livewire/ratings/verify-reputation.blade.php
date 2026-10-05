<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Verify reputation record</h1>
        <p class="mt-1 text-sm text-slate-600">
            Upload a reputation file previously exported from a UniMarket profile. We will check its digital signature
            against our records - no account needed.
        </p>
    </div>

    @if ($isValid === true)
        <x-alert type="success">
            <span class="font-semibold">&check; VALID REPUTATION RECORD</span>
            <p class="mt-1">{{ $reason }}</p>
        </x-alert>
    @elseif ($isValid === false)
        <x-alert type="error">
            <span class="font-semibold">&cross; INVALID / MODIFIED RECORD</span>
            <p class="mt-1">{{ $reason }}</p>
        </x-alert>
    @endif

    <form wire:submit="verify" novalidate class="card space-y-5 p-5 sm:p-6">
        <div>
            <label for="reputation-file" class="form-label">Reputation export file</label>
            <p class="form-hint mb-2">The .json file downloaded from "Export Reputation &amp; History" on a profile.</p>

            <label
                for="reputation-file"
                class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition-colors hover:border-brand-400 hover:bg-brand-50 dark:hover:bg-brand-500/10"
            >
                <x-app-icon name="upload" class="h-8 w-8 text-brand-700" />
                <span class="text-sm font-semibold text-ink">Tap to choose a file</span>
                <span class="text-xs text-slate-600">JSON, up to 2 MB</span>
                <input id="reputation-file" type="file" wire:model="file" accept="application/json,.json" class="sr-only" />
            </label>

            <div wire:loading wire:target="file" class="mt-2 flex items-center gap-2 text-sm font-medium text-brand-800 dark:text-brand-300" role="status">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Uploading...
            </div>

            @if ($file)
                <p class="mt-2 flex items-center gap-1.5 text-sm text-ink">
                    <x-app-icon name="check-circle" class="h-4 w-4 text-accent-700" /> {{ $file->getClientOriginalName() }}
                </p>
            @endif

            <x-form-error name="file" />
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="verify,file">
                <span wire:loading.remove wire:target="verify">Verify</span>
                <span wire:loading wire:target="verify">Verifying...</span>
            </button>
        </div>
    </form>
</div>
