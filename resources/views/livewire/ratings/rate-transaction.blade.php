<div>
    @if ($existing)
        <div class="rounded-xl border border-accent-200 bg-accent-50 p-4 text-sm" aria-live="polite">
            <p class="flex items-center gap-1.5 font-semibold text-accent-800">
                <x-star-icon class="h-4 w-4" /> You rated {{ $ratedUser->name }} {{ $existing->stars }} out of 5
            </p>
            @if ($existing->comment)
                <p class="mt-1 whitespace-pre-line break-words text-gray-700">&ldquo;{{ $existing->comment }}&rdquo;</p>
            @endif
        </div>
    @elseif ($canRate)
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-4 sm:p-5 dark:border-brand-500/30 dark:bg-brand-500/10" x-data="{ hover: 0 }">
            <h3 class="text-sm font-semibold text-ink">Rate your experience with {{ $ratedUser->name }}</h3>
            <p class="mt-0.5 text-xs text-slate-600">Your transaction is complete. Let other students know how it went.</p>

            <form wire:submit="submit" novalidate class="mt-3 space-y-3">
                <div class="flex items-center gap-1" role="radiogroup" aria-label="Star rating">
                    @for ($value = 1; $value <= 5; $value++)
                        <button
                            type="button"
                            wire:click="$set('stars', {{ $value }})"
                            x-on:mouseenter="hover = {{ $value }}"
                            x-on:mouseleave="hover = 0"
                            role="radio"
                            aria-checked="{{ $stars === $value ? 'true' : 'false' }}"
                            aria-label="{{ $value }} {{ \Illuminate\Support\Str::plural('star', $value) }}"
                            class="rounded p-1 text-warn-500 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                            x-bind:class="(hover || {{ $stars }}) >= {{ $value }} ? 'text-warn-500' : 'text-slate-300'"
                        >
                            <x-star-icon class="h-7 w-7" />
                        </button>
                    @endfor
                    <span class="ml-1 text-sm font-medium text-slate-700" x-text="hover || {{ $stars }} ? (hover || {{ $stars }}) + ' / 5' : ''"></span>
                </div>
                <x-form-error name="stars" />

                <div>
                    <label for="rating-comment-{{ $transaction->id }}" class="sr-only">Comment (optional)</label>
                    <textarea
                        id="rating-comment-{{ $transaction->id }}"
                        wire:model="comment"
                        rows="2"
                        maxlength="1000"
                        placeholder="Add a comment (optional)..."
                        class="form-input"
                    ></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Submit rating</span>
                    <span wire:loading wire:target="submit">Submitting...</span>
                </button>
            </form>
        </div>
    @endif
</div>
