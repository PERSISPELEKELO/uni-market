<div class="mx-auto max-w-3xl">
    <a href="{{ route('listings.index') }}" class="mb-4 inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand-800 dark:hover:text-brand-300">
        <x-app-icon name="arrow-left" class="h-4 w-4" /> Back to marketplace
    </a>

    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Sell an item</h1>
        <p class="mt-1 text-sm text-slate-600">
            Tell students what you are selling. Clear photos and an honest description help it sell faster.
        </p>
    </div>

    @if ($canSell)
        <form wire:submit="save" novalidate class="card space-y-6 p-4 sm:p-8">
            @if ($errors->any())
                <x-alert type="error">Please fix the highlighted fields below and try again.</x-alert>
            @endif

            @include('livewire.marketplace.partials.listing-fields')

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('listings.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="button" wire:click.prevent="saveAsDraft" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveAsDraft,save,form.images">
                    <span wire:loading.remove wire:target="saveAsDraft">Save as draft</span>
                    <span wire:loading wire:target="saveAsDraft">Saving...</span>
                </button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,saveAsDraft,form.images">
                    <span wire:loading.remove wire:target="save">Publish listing</span>
                    <span wire:loading wire:target="save">Publishing...</span>
                </button>
            </div>
        </form>
    @else
        <div class="card space-y-4 p-6 text-center sm:p-10">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-500/10">
                <x-app-icon name="shield" class="h-6 w-6" />
            </div>

            @if (auth()->user()->isAwaitingEmailVerification())
                <h2 class="text-lg font-semibold text-ink">Verify your email to continue</h2>
                <p class="mx-auto max-w-sm text-sm text-slate-600">Please confirm your email address before selling on UniMarket.</p>
                <a href="{{ route('verification.notice') }}" class="btn btn-primary">Verify my email</a>
            @else
                <h2 class="text-lg font-semibold text-ink">Only verified ZUT students can sell</h2>
                <p class="mx-auto max-w-sm text-sm text-slate-600">
                    Upload a clear photo of the front of your student ID card to get verified - it usually takes 24 hours.
                </p>

                <p class="text-sm font-medium text-ink">
                    Your status:
                    @switch(auth()->user()->student_verification_status)
                        @case(\App\Models\User::STUDENT_VERIFICATION_PENDING)
                            <span class="badge badge-warning">Under review</span>
                            @break
                        @case(\App\Models\User::STUDENT_VERIFICATION_REJECTED)
                            <span class="badge badge-danger">Not approved - please resubmit</span>
                            @break
                        @case(\App\Models\User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED)
                            <span class="badge badge-warning">Needs a new photo</span>
                            @break
                        @default
                            <span class="badge badge-neutral">Not submitted yet</span>
                    @endswitch
                </p>

                <a href="{{ route('verification.student.form') }}" class="btn btn-primary">Upload my student ID</a>
            @endif
        </div>
    @endif
</div>
