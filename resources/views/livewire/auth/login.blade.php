<div class="mx-auto grid w-full max-w-5xl grid-cols-1 gap-8 py-4 sm:py-8 lg:grid-cols-2 lg:items-center lg:gap-12">
    <div class="hidden lg:block">
        <a href="{{ route('login') }}" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-brand-900 dark:text-brand-300">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</span>
            UniMarket
        </a>
        <h1 class="mt-6 text-3xl font-bold leading-tight tracking-tight text-ink">
            Buy and sell with <span class="text-brand-700 dark:text-brand-400">verified ZUT students</span>.
        </h1>
        <p class="mt-2 text-slate-600">Textbooks, phones, dorm gear and more - with protected handovers.</p>

        {{-- Simple brand illustration: overlapping rounded shapes only, no photos. --}}
        <div class="relative mt-10 h-56 w-full max-w-sm" aria-hidden="true">
            <div class="absolute left-0 top-6 h-32 w-44 rounded-2xl bg-brand-100 dark:bg-brand-500/10"></div>
            <div class="absolute left-16 top-0 h-36 w-48 rotate-3 rounded-2xl bg-brand-700 shadow-lg"></div>
            <div class="absolute left-10 top-20 h-24 w-36 -rotate-6 rounded-2xl bg-accent-600 shadow-md"></div>
            <div class="absolute left-28 top-10 flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-lg dark:bg-slate-200">
                <x-app-icon name="check-circle" class="h-9 w-9 text-accent-700" />
            </div>
        </div>

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
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">
                Buy and sell with <span class="text-brand-700 dark:text-brand-400">verified ZUT students</span>.
            </h1>
            <p class="mt-1 text-sm text-slate-600">Textbooks, phones, dorm gear and more - with protected handovers.</p>
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

        <div class="relative pt-2">
            <div class="absolute inset-0 flex items-center" aria-hidden="true"><div class="w-full border-t border-slate-200 dark:border-slate-300"></div></div>
            <div class="relative flex justify-center"><span class="bg-white px-3 text-xs text-slate-500 dark:bg-slate-200">New here?</span></div>
        </div>

        <a href="{{ route('register') }}" class="btn btn-secondary btn-block">Create new account</a>
    </x-card>
</div>
