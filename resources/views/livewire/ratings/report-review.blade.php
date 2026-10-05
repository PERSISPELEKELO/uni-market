<div>
    @if ($submitted)
        <span class="inline-flex items-center gap-1 text-xs text-slate-500">
            <x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Reported
        </span>
    @elseif ($open)
        <div class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3" x-data>
            <label class="block text-xs font-semibold text-ink" for="report-reason-{{ $rating->id }}">Why are you reporting this review?</label>
            <select id="report-reason-{{ $rating->id }}" wire:model="reason" class="form-input">
                <option value="">Choose a reason&hellip;</option>
                <option value="offensive">Offensive content</option>
                <option value="spam">Spam</option>
                <option value="false">False or inappropriate content</option>
                <option value="harassment">Harassment</option>
                <option value="other">Other</option>
            </select>
            <x-form-error name="reason" />

            <label class="sr-only" for="report-details-{{ $rating->id }}">Additional details (optional)</label>
            <textarea id="report-details-{{ $rating->id }}" wire:model="details" rows="2" maxlength="1000" placeholder="Additional details (optional)..." class="form-input"></textarea>

            <div class="flex items-center gap-2">
                <button type="button" wire:click="submit" class="btn btn-danger-outline btn-sm" wire:loading.attr="disabled" wire:target="submit">
                    Submit report
                </button>
                <button type="button" wire:click="toggle" class="btn btn-secondary btn-sm">Cancel</button>
            </div>
        </div>
    @else
        <button
            type="button"
            wire:click="toggle"
            class="inline-flex items-center gap-1 rounded p-1 text-xs text-slate-500 hover:text-warn-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700 dark:hover:text-warn-300"
            title="Report this review"
            aria-label="Report this review"
        >
            <x-app-icon name="flag" class="h-3.5 w-3.5" />
        </button>
    @endif
</div>
