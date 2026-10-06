<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">My appeals</h1>
        <p class="mt-1 text-sm text-slate-600">Contest a moderation decision on your account, and see the outcome of appeals you've already submitted.</p>
    </div>

    @if ($verificationRejected || $suspendedListings->isNotEmpty())
        <section class="card space-y-5 p-5 sm:p-6" aria-labelledby="appeal-form-heading">
            <h2 id="appeal-form-heading" class="text-sm font-semibold text-ink">Appeal a decision</h2>

            @if ($verificationRejected)
                <div class="rounded-xl border border-warn-200 bg-warn-50 p-4 dark:border-warn-500/30 dark:bg-warn-500/10">
                    <p class="text-sm font-semibold text-ink">Your student verification was rejected.</p>
                    @if (auth()->user()->student_verification_rejection_reason)
                        <p class="mt-1 text-sm text-slate-700">{{ auth()->user()->student_verification_rejection_reason }}</p>
                    @endif
                    <label for="reason" class="form-label mt-3">Why should this be reconsidered?</label>
                    <textarea id="reason" wire:model="reason" rows="3" maxlength="2000" class="form-input" placeholder="Explain what was wrong with the rejection, or provide more context..."></textarea>
                    <x-form-error name="reason" />
                    <button type="button" wire:click="submitVerificationAppeal" class="btn btn-primary btn-sm mt-3" wire:loading.attr="disabled" wire:target="submitVerificationAppeal">
                        Submit appeal
                    </button>
                </div>
            @endif

            @foreach ($suspendedListings as $listing)
                <div class="rounded-xl border border-warn-200 bg-warn-50 p-4 dark:border-warn-500/30 dark:bg-warn-500/10">
                    <p class="text-sm font-semibold text-ink">Your listing "{{ $listing->title }}" was suspended.</p>
                    <label for="reason-{{ $listing->id }}" class="form-label mt-3">Why should this be reconsidered?</label>
                    <textarea id="reason-{{ $listing->id }}" wire:model="reason" rows="3" maxlength="2000" class="form-input" placeholder="Explain why this listing should be restored..."></textarea>
                    <x-form-error name="reason" />
                    <button type="button" wire:click="submitListingAppeal({{ $listing->id }})" class="btn btn-primary btn-sm mt-3" wire:loading.attr="disabled" wire:target="submitListingAppeal">
                        Submit appeal
                    </button>
                </div>
            @endforeach
        </section>
    @endif

    <section class="card space-y-4 p-5 sm:p-6" aria-labelledby="appeal-history-heading">
        <h2 id="appeal-history-heading" class="text-sm font-semibold text-ink">Your appeal history</h2>

        @forelse ($appeals as $appeal)
            @php
                $status = $appeal->status instanceof \App\Enums\AppealStatus ? $appeal->status->value : $appeal->status;
                $statusLabel = ucfirst(strtolower(str_replace('_', ' ', $status)));
                $statusColor = match ($status) {
                    'PENDING' => 'badge-warning',
                    'UNDER_REVIEW' => 'badge-info',
                    'UPHELD' => 'badge-danger',
                    'OVERTURNED' => 'badge-success',
                    default => 'badge-neutral',
                };
            @endphp
            <div class="border-t border-slate-100 pt-4 first:border-t-0 first:pt-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium text-ink">Concerning: {{ $appeal->target_type }} #{{ $appeal->target_id }}</p>
                    <span class="badge {{ $statusColor }}">{{ $statusLabel }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-700">{{ $appeal->reason }}</p>
                @if ($appeal->governance_notes)
                    <p class="mt-1 text-xs text-slate-600"><span class="font-semibold">Response:</span> {{ $appeal->governance_notes }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">Submitted {{ $appeal->created_at->diffForHumans() }}</p>
            </div>
        @empty
            <x-empty-state icon="shield" title="No appeals submitted" description="If a moderation decision affects your account, you'll be able to appeal it here." />
        @endforelse
    </section>
</div>
