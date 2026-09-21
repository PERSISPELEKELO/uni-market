<div class="mx-auto w-full max-w-md py-4 sm:py-8">
    <div class="card space-y-6 p-6 sm:p-8">

        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-600">Log in to buy, sell and chat with students on campus.</p>
        </div>

        <form wire:submit="login" novalidate class="space-y-5">
            @error('credentials')
                <x-alert type="error">{{ $message }}</x-alert>
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
                    placeholder="you@example.com"
                    class="form-input"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                />
                <x-form-error name="email" />
            </div>

            <div>
                <label for="password" class="form-label">Password</label>
                <x-password-input id="password" wire:model="password" autocomplete="current-password" placeholder="Your password" />
                <x-form-error name="password" />
            </div>

            <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-slate-800">
                <input type="checkbox" wire:model="remember" class="h-5 w-5 rounded border-slate-400 text-brand-700 focus:ring-brand-700" />
                <span>Keep me logged in on this device</span>
            </label>

            <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">Log in</span>
                <span wire:loading wire:target="login">Logging in...</span>
            </button>
        </form>

        <p class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
            New to UniMarket?
            <a href="{{ route('register') }}" class="font-semibold text-brand-800 underline underline-offset-2 hover:text-brand-900">Create an account</a>
        </p>
    </div>
</div>
