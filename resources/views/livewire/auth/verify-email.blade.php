<div class="mx-auto w-full max-w-md py-4 sm:py-8">
    <div class="card space-y-5 p-6 sm:p-8">

        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                <x-app-icon name="shield" class="h-6 w-6" />
            </div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">Verify your email</h1>
        </div>

        @if ($user->hasVerifiedEmail())
            <x-alert type="success">Your email address <strong>{{ $user->email }}</strong> is verified.</x-alert>
            <a href="{{ route('listings.index') }}" class="btn btn-primary btn-block">Continue to the marketplace</a>
        @else
            <p class="text-sm text-slate-700">
                We sent a verification link to <strong class="break-all">{{ $user->email }}</strong>. Open it to confirm your address and earn the
                <span class="font-semibold">Official Student</span> badge, which tells buyers and sellers you are a real member of the community.
            </p>

            @if ($sent)
                <x-alert type="success">A new verification link is on its way. It can take a minute to arrive.</x-alert>
            @endif

            @error('resend')
                <x-alert type="warning">{{ $message }}</x-alert>
            @enderror

            <button type="button" wire:click="resend" wire:loading.attr="disabled" wire:target="resend" class="btn btn-primary btn-block">
                <span wire:loading.remove wire:target="resend">Send me a new link</span>
                <span wire:loading wire:target="resend">Sending...</span>
            </button>

            <p class="text-center text-xs text-slate-600">Wrong address? Contact support to have it corrected.</p>
        @endif
    </div>
</div>
