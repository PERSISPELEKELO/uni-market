<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="UniMarket is the campus marketplace where verified students buy, sell and swap textbooks, electronics, dorm gear and more.">
    <meta name="theme-color" content="#4338ca">

    <title>{{ $title ?? 'UniMarket - Campus Student Marketplace' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col">

    <a href="#main-content" class="sr-only z-50 rounded-lg bg-white px-4 py-2 font-semibold text-brand-800 shadow focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Skip to main content
    </a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur" x-data="{ mobileOpen: false }" x-on:keydown.escape.window="mobileOpen = false">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-2">

                <div class="flex min-w-0 items-center gap-6">
                    <a href="{{ route('listings.index') }}" class="flex flex-shrink-0 items-center gap-2 text-lg font-bold tracking-tight text-brand-900">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-700 font-bold text-white shadow-sm" aria-hidden="true">U</span>
                        <span>UniMarket</span>
                    </a>

                    <nav class="hidden items-center gap-1 md:flex" aria-label="Main navigation">
                        <a href="{{ route('listings.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('listings.index', 'listings.show')]) @if(request()->routeIs('listings.index')) aria-current="page" @endif>Marketplace</a>
                        @auth
                            <a href="{{ route('listings.mine') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('listings.mine', 'listings.edit')]) @if(request()->routeIs('listings.mine')) aria-current="page" @endif>My listings</a>
                            <a href="{{ route('chat.index') }}" @class(['nav-link inline-flex items-center gap-1.5', 'nav-link-active' => request()->routeIs('chat.*')]) @if(request()->routeIs('chat.*')) aria-current="page" @endif>
                                Messages
                                @if ($unreadMessageCount > 0)
                                    <span class="badge border-accent-700 bg-accent-700 px-2 py-0 text-white">{{ $unreadMessageCount }}<span class="sr-only"> unread</span></span>
                                @endif
                            </a>
                            <a href="{{ route('transactions.tracker') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('transactions.*')]) @if(request()->routeIs('transactions.*')) aria-current="page" @endif>My transactions</a>
                        @endauth
                    </nav>
                </div>

                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('listings.create') }}" class="btn btn-primary btn-sm sm:min-h-11 sm:px-4 sm:text-sm">
                            <x-app-icon name="plus" class="h-4 w-4" />
                            <span>Sell item</span>
                        </a>

                        <div class="relative hidden md:block" x-data="{ menuOpen: false }" x-on:click.outside="menuOpen = false" x-on:keydown.escape="menuOpen = false">
                            <button type="button" class="btn btn-secondary btn-sm sm:min-h-11" x-on:click="menuOpen = !menuOpen" x-bind:aria-expanded="menuOpen" aria-haspopup="true">
                                <x-app-icon name="user" class="h-5 w-5" />
                                <span class="max-w-[9rem] truncate">{{ auth()->user()->name }}</span>
                                <x-app-icon name="chevron-down" class="h-4 w-4" />
                            </button>

                            <div x-cloak x-show="menuOpen" x-transition.opacity class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                                <div class="border-b border-slate-100 px-3 pb-3 pt-2">
                                    <p class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs text-slate-600">{{ auth()->user()->email }}</p>
                                    @if (auth()->user()->is_verified)
                                        <span class="badge badge-success mt-2"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Official Student</span>
                                    @endif
                                </div>
                                <a href="{{ route('account') }}" class="mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="user" class="h-5 w-5" /> My account
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                        <x-app-icon name="logout" class="h-5 w-5" /> Log out
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="nav-link">Log in</a>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm hidden sm:inline-flex sm:min-h-11 sm:px-4 sm:text-sm">Join UniMarket</a>
                    @endauth

                    <button type="button" class="btn btn-secondary btn-sm min-h-11 min-w-11 px-2 md:hidden" x-on:click="mobileOpen = !mobileOpen" x-bind:aria-expanded="mobileOpen" aria-controls="mobile-menu">
                        <span class="sr-only">Toggle menu</span>
                        <x-app-icon name="menu" x-show="!mobileOpen" />
                        <x-app-icon name="x" x-cloak x-show="mobileOpen" />
                    </button>
                </div>
            </div>
        </div>

        <nav id="mobile-menu" x-cloak x-show="mobileOpen" x-transition.opacity class="border-t border-slate-200 bg-white md:hidden" aria-label="Mobile navigation">
            <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3 sm:px-6">
                <a href="{{ route('listings.index') }}" class="nav-link py-3">Marketplace</a>
                @auth
                    <a href="{{ route('listings.mine') }}" class="nav-link py-3">My listings</a>
                    <a href="{{ route('chat.index') }}" class="nav-link flex items-center justify-between py-3">
                        <span>Messages</span>
                        @if ($unreadMessageCount > 0)
                            <span class="badge border-accent-700 bg-accent-700 text-white">{{ $unreadMessageCount }}<span class="sr-only"> unread</span></span>
                        @endif
                    </a>
                    <a href="{{ route('transactions.tracker') }}" class="nav-link py-3">My transactions</a>
                    <div class="mt-2 border-t border-slate-100 pt-3">
                        <p class="truncate px-3 text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
                        <p class="truncate px-3 text-xs text-slate-600">{{ auth()->user()->email }}</p>
                        <a href="{{ route('account') }}" class="nav-link mt-1 flex items-center gap-2 py-3">
                            <x-app-icon name="user" class="h-5 w-5" /> My account
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav-link flex w-full items-center gap-2 py-3 text-left">
                                <x-app-icon name="logout" class="h-5 w-5" /> Log out
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary mt-2">Join UniMarket</a>
                @endauth
            </div>
        </nav>
    </header>

    @auth
        @if (auth()->user()->isAwaitingEmailVerification() && ! request()->routeIs('verification.notice'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-alert type="warning">
                    Confirm your email address to earn the Official Student badge.
                    <a href="{{ route('verification.notice') }}" class="font-semibold underline underline-offset-2">Verify now</a>
                </x-alert>
            </div>
        @endif
    @endauth

    @foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'status' => 'info'] as $flashKey => $flashType)
        @if (session()->has($flashKey))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-alert :type="$flashType">{{ session($flashKey) }}</x-alert>
            </div>
        @endif
    @endforeach

    <main id="main-content" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        {{ $slot }}
    </main>

    <footer class="mt-auto border-t border-slate-200 bg-white py-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-center text-sm text-slate-600 sm:px-6 md:flex-row md:items-center md:justify-between md:text-left lg:px-8">
            <p>&copy; {{ date('Y') }} UniMarket. The student marketplace for campus communities.</p>
            <ul class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 font-medium text-slate-700">
                <li class="flex items-center gap-1.5"><x-app-icon name="shield" class="h-4 w-4 text-accent-700" /> Verified students</li>
                <li>Escrow protection</li>
                <li>Moderated disputes</li>
            </ul>
        </div>
    </footer>

    {{-- Notifications dispatched from Livewire components: $this->dispatch('notify', message: '...', type: 'success') --}}
    <div
        x-data="{
            toasts: [],
            add(detail) {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, type: detail.type ?? 'success', message: detail.message });
                setTimeout(() => this.remove(id), 7000);
            },
            remove(id) {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            },
        }"
        x-on:notify.window="add($event.detail)"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 mx-auto flex max-w-md flex-col gap-2 px-4"
        aria-live="polite"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div class="alert pointer-events-auto shadow-lg" x-bind:class="'alert-' + toast.type" x-bind:role="toast.type === 'error' ? 'alert' : 'status'">
                <div class="min-w-0 flex-1 break-words" x-text="toast.message"></div>
                <button type="button" class="-m-1 flex-shrink-0 rounded p-1 hover:bg-black/5" x-on:click="remove(toast.id)">
                    <span class="sr-only">Dismiss</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
