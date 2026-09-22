@php
    $status = $user->student_verification_status;
@endphp

<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <a href="{{ route('account') }}" class="mb-2 inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand-800">
            <x-app-icon name="arrow-left" class="h-4 w-4" /> Back to my account
        </a>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Student verification</h1>
        <p class="mt-1 text-sm text-slate-600">
            Upload a photo or scan of your student ID card so an administrator can confirm your student status. This is separate from confirming your email address.
        </p>
    </div>

    @if (! $user->hasVerifiedEmail())
        <x-alert type="warning">
            Please verify your email address first.
            <a href="{{ route('verification.notice') }}" class="font-semibold underline underline-offset-2">Verify your email</a>
        </x-alert>
    @elseif ($status === \App\Models\User::STUDENT_VERIFICATION_VERIFIED)
        <x-alert type="success">
            <span class="font-semibold">Verified Student.</span>
            Your account was approved on {{ $user->student_verification_reviewed_at?->format('F j, Y') }}.
        </x-alert>
    @elseif ($status === \App\Models\User::STUDENT_VERIFICATION_PENDING)
        <x-alert type="info">
            Your document submitted on {{ $user->student_verification_submitted_at?->format('F j, Y \a\t g:i A') }} is waiting for an administrator to review it.
        </x-alert>
    @else
        @if ($status === \App\Models\User::STUDENT_VERIFICATION_REJECTED || $status === \App\Models\User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED)
            <x-alert type="warning">
                <span class="font-semibold">{{ $status === \App\Models\User::STUDENT_VERIFICATION_REJECTED ? 'Your verification was not approved.' : 'Please resubmit your document.' }}</span>
                @if ($user->student_verification_rejection_reason)
                    <p class="mt-1">{{ $user->student_verification_rejection_reason }}</p>
                @endif
            </x-alert>
        @endif

        <form wire:submit="submit" novalidate class="card space-y-5 p-5 sm:p-6">
            <div>
                <label for="document" class="form-label">Student ID document</label>
                <p class="form-hint mb-2">A clear photo or scan of your official student ID card. It must show your name and student ID number.</p>

                <label
                    for="document"
                    x-data="{ isDropping: false }"
                    x-on:dragover.prevent="isDropping = true"
                    x-on:dragleave.prevent="isDropping = false"
                    x-on:drop.prevent="isDropping = false; $refs.docInput.files = $event.dataTransfer.files; $refs.docInput.dispatchEvent(new Event('change', { bubbles: true }));"
                    class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition-colors hover:border-brand-400 hover:bg-brand-50"
                    x-bind:class="{ 'border-brand-600 bg-brand-50': isDropping }"
                >
                    <x-app-icon name="upload" class="h-8 w-8 text-brand-700" />
                    <span class="text-sm font-semibold text-ink">Tap to choose a file, or drag and drop it here</span>
                    <span class="text-xs text-slate-600">JPG, PNG or PDF, up to 5 MB</span>
                    <input id="document" x-ref="docInput" type="file" wire:model="document" accept="image/jpeg,image/png,application/pdf" class="sr-only" />
                </label>

                <div wire:loading wire:target="document" class="mt-2 flex items-center gap-2 text-sm font-medium text-brand-800" role="status">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Uploading...
                </div>

                @if ($document)
                    <p class="mt-2 flex items-center gap-1.5 text-sm text-ink">
                        <x-app-icon name="check-circle" class="h-4 w-4 text-accent-700" /> {{ $document->getClientOriginalName() }}
                    </p>
                @endif

                <x-form-error name="document" />
            </div>

            <div class="flex justify-end border-t border-slate-100 pt-5">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submit,document">
                    <span wire:loading.remove wire:target="submit">Submit for review</span>
                    <span wire:loading wire:target="submit">Submitting...</span>
                </button>
            </div>
        </form>
    @endif

    @if ($history->isNotEmpty())
        <section class="card space-y-3 p-5 sm:p-6" aria-labelledby="history-heading">
            <h2 id="history-heading" class="text-sm font-semibold text-ink">Submission history</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($history as $attempt)
                    <li class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ $attempt->original_filename }}</p>
                            <p class="text-xs text-slate-600">Submitted {{ $attempt->submitted_at->diffForHumans() }}</p>
                        </div>
                        <x-document-status-badge :status="$attempt->status" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
