<div class="mx-auto grid w-full max-w-5xl grid-cols-1 gap-8 py-4 sm:py-8 lg:grid-cols-2 lg:items-center lg:gap-12">
    <div class="hidden lg:block">
        <a href="{{ route('listings.index') }}" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-brand-900 dark:text-brand-300">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</span>
            UniMarket
        </a>
        <h1 class="mt-6 text-2xl font-bold tracking-tight text-ink">Welcome back</h1>
        <p class="mt-2 text-slate-600">Log in to buy, sell and chat with verified students on your campus.</p>

        <div class="card mt-6 flex gap-8 p-5">
            <div>
                <p class="text-2xl font-bold text-ink">{{ number_format($stats['students']) }}+</p>
                <p class="text-xs text-slate-600">Students</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-ink">{{ number_format($stats['listings']) }}+</p>
                <p class="text-xs text-slate-600">Active listings</p>
            </div>
        </div>
    </div>

    <x-card padding="p-6 sm:p-8" class="space-y-6">
        <div class="text-center lg:hidden">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-600">Log in to buy, sell and chat with students on campus.</p>
        </div>

        <form wire:submit="login" novalidate class="space-y-5">
            @error('credentials')
                <x-alert type="error">{{ $message }}</x-alert>
            @enderror

            <x-input
                label="Email address"
                name="email"
                type="email"
                icon="mail"
                wire:model="email"
                autocomplete="email"
                inputmode="email"
                autocapitalize="none"
                spellcheck="false"
                oninput="this.value = this.value.toLowerCase()"
                placeholder="you@example.com"
            />

            <div>
                <label for="password" class="form-label">Password</label>
                <x-password-input id="password" wire:model="password" autocomplete="current-password" placeholder="Your password" icon="lock" />
                <x-form-error name="password" />
                <a href="{{ route('password.request') }}" class="mt-1.5 inline-block text-sm font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2 hover:text-brand-900 dark:hover:text-brand-200">Forgot password?</a>
            </div>

            <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-slate-800">
                <input type="checkbox" wire:model="remember" class="h-5 w-5 rounded border-slate-400 text-brand-700 focus:ring-brand-700" />
                <span>Keep me logged in on this device</span>
            </label>

            <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in...</span>
            </button>
        </form>

        <p class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
            New to UniMarket?
            <a href="{{ route('register') }}" class="font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2 hover:text-brand-900 dark:hover:text-brand-200">Create an account</a>
        </p>
    </x-card>
</div>
