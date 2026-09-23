<div class="mx-auto w-full max-w-md py-4 sm:py-8">
    <div class="card space-y-6 p-6 sm:p-8">

        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">Forgot your password?</h1>
            <p class="mt-1 text-sm text-slate-600">Enter your email and we will send you a link to choose a new one.</p>
        </div>

        @if ($sent)
            <x-alert type="success">
                If an account exists for that email address, we have sent a password reset link. Check your inbox (and spam folder). The link expires in {{ config('auth.passwords.users.expire') }} minutes.
            </x-alert>
            <a href="{{ route('login') }}" class="btn btn-secondary btn-block">Back to log in</a>
        @else
            <form wire:submit="sendLink" novalidate class="space-y-5">
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
                        oninput="this.value = this.value.toLowerCase()"
                        placeholder="you@example.com"
                        class="form-input"
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    />
                    <x-form-error name="email" />
                </div>

                <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="sendLink">
                    <span wire:loading.remove wire:target="sendLink">Email me a reset link</span>
                    <span wire:loading wire:target="sendLink">Sending...</span>
                </button>
            </form>

            <p class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
                Remembered it?
                <a href="{{ route('login') }}" class="font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2 hover:text-brand-900 dark:hover:text-brand-200">Log in</a>
            </p>
        @endif
    </div>
</div>
