@php
    $isSelf = auth()->id() === $user->id;
@endphp

<div class="mx-auto max-w-2xl space-y-6">
    <a href="{{ route('listings.index') }}" class="inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand-800">
        <x-app-icon name="arrow-left" class="h-4 w-4" /> Back to marketplace
    </a>

    <div class="card p-5 sm:p-8">
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:text-left">
            <x-avatar :user="$user" class="h-20 w-20 flex-shrink-0 text-2xl" />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                    <h1 class="break-words text-xl font-bold text-ink">{{ $user->name }}</h1>
                    @if ($user->is_verified)
                        <span class="badge badge-success"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Official Student</span>
                    @endif
                </div>

                <dl class="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm text-slate-600 sm:justify-start">
                    @if ($user->programme)
                        <div class="flex items-center gap-1">
                            <dt class="sr-only">Programme</dt>
                            <dd>{{ $user->programme }}</dd>
                        </div>
                    @endif
                    @if ($user->business_type)
                        <div class="flex items-center gap-1">
                            <dt class="sr-only">Business type</dt>
                            <dd class="badge badge-brand">{{ $user->business_type }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="sr-only">Member since</dt>
                        <dd>Member since {{ $user->created_at->format('M Y') }}</dd>
                    </div>
                </dl>

                @if (! $isSelf)
                    <button type="button" wire:click="message" class="btn btn-primary btn-sm mt-4">
                        <x-app-icon name="chat" class="h-4 w-4" /> {{ auth()->check() ? 'Message' : 'Log in to message' }}
                    </button>
                @endif
            </div>
        </div>

        @if ($user->bio)
            <div class="mt-6 border-t border-slate-100 pt-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-600">About</h2>
                <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-800">{{ $user->bio }}</p>
            </div>
        @endif
    </div>

    @if ($activeListingsCount > 0)
        <div class="card p-5 text-center sm:p-6">
            <p class="text-sm text-slate-700">
                <span class="font-semibold text-ink">{{ $user->name }}</span> has
                <a href="{{ route('listings.index') }}" class="font-semibold text-brand-800 underline underline-offset-2">{{ $activeListingsCount }} {{ \Illuminate\Support\Str::plural('item', $activeListingsCount) }}</a>
                for sale right now.
            </p>
        </div>
    @endif
</div>
