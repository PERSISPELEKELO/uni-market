<div class="mx-auto w-full max-w-md py-4 sm:py-8">
    <div class="card space-y-6 p-6 sm:p-8">

        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">Choose a new password</h1>
            <p class="mt-1 text-sm text-slate-600">Pick a strong password you have not used elsewhere.</p>
        </div>

        <form wire:submit="resetPassword" novalidate class="space-y-5">
            @error('email')
                @if (! $errors->has('password'))
                    <x-alert type="error">{{ $message }}</x-alert>
                @endif
            @enderror

            <div>
                <label for="email" class="form-label">Email address</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    class="form-input"
                    @error('email') aria-invalid="true" @enderror
                />
            </div>

            <div>
                <label for="password" class="form-label">New password</label>
                <x-password-input id="password" wire:model="password" autocomplete="new-password" placeholder="At least 8 characters" />
                <p class="form-hint">Use at least 8 characters, with a letter and a number.</p>
                <x-form-error name="password" />
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirm new password</label>
                <x-password-input id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" placeholder="Type it again" />
            </div>

            <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="resetPassword">
                <span wire:loading.remove wire:target="resetPassword">Reset password</span>
                <span wire:loading wire:target="resetPassword">Resetting...</span>
            </button>
        </form>

        <p class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
            Link not working?
            <a href="{{ route('password.request') }}" class="font-semibold text-brand-800 underline underline-offset-2 hover:text-brand-900">Request a new one</a>
        </p>
    </div>
</div>
