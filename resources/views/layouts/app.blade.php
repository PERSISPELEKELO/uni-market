<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-initial-theme="{{ auth()->user()->theme ?? '' }}">
<head>
    <meta charset="utf-8">
    <script>
        (function () {
            // An authenticated user's saved theme (set server-side above)
            // always wins over this browser's localStorage, so the setting
            // follows them across devices; localStorage is only the
            // fallback for guests and for 'auto' mode's last resolution.
            var initial = document.documentElement.dataset.initialTheme || '';
            var stored = null;
            try {
                stored = localStorage.getItem('uniMarketTheme');
            } catch (e) {}
            var theme = initial || stored || 'auto';
            var mql = window.matchMedia('(prefers-color-scheme: dark)');

            function apply() {
                var isDark = theme === 'dark' || (theme === 'auto' && mql.matches);
                document.documentElement.classList.toggle('dark', isDark);
            }

            apply();
            mql.addEventListener('change', function () {
                if (theme === 'auto') {
                    apply();
                }
            });

            window.uniMarketTheme = {
                set: function (next) {
                    theme = next;
                    try {
                        localStorage.setItem('uniMarketTheme', next);
                    } catch (e) {}
                    apply();
                },
                toggle: function () {
                    window.uniMarketTheme.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
                },
            };
        })();
    </script>
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
<body class="flex min-h-screen flex-col" x-data="{ mobileOpen: false }" x-on:keydown.escape.window="mobileOpen = false">

    <a href="#main-content" class="sr-only z-50 rounded-lg bg-white px-4 py-2 font-semibold text-brand-800 dark:bg-slate-200 dark:text-brand-300 shadow focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Skip to main content
    </a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur dark:bg-slate-200/95">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-2">

                <div class="flex min-w-0 items-center gap-6">
                    <a href="{{ route('listings.index') }}" class="flex flex-shrink-0 items-center gap-2 text-lg font-bold tracking-tight text-brand-900 dark:text-brand-300">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-700 font-bold text-white shadow-sm" aria-hidden="true">U</span>
                        <span>UniMarket</span>
                    </a>

                    {{-- Left side is logo + Home only - My Activity and
                         Messages moved into the right-side icon group below,
                         matching Facebook's layout. Guests never see this
                         nav: it only links to marketplace pages, which are
                         all behind login now. --}}
                    @auth
                        <nav class="hidden items-center gap-1 md:flex" aria-label="Main navigation">
                            <a
                                href="{{ route('listings.index') }}"
                                @class(['relative inline-flex min-h-11 min-w-11 items-center justify-center border-b-2 border-transparent text-slate-600 transition-colors hover:text-brand-800', 'border-brand-700 text-brand-800' => request()->routeIs('listings.index', 'listings.show')])
                                @if(request()->routeIs('listings.index')) aria-current="page" @endif
                                aria-label="Home"
                                title="Home"
                            >
                                <x-app-icon name="home" class="h-5 w-5" />
                                <span class="sr-only">Home</span>
                            </a>
                        </nav>
                    @endauth
                </div>

                @auth
                    <form action="{{ route('listings.index') }}" method="GET" class="mx-4 hidden min-w-0 max-w-md flex-1 lg:flex">
                        <label for="top-search" class="sr-only">Search listings</label>
                        <div class="relative w-full">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <x-app-icon name="search" class="h-4 w-4" />
                            </span>
                            <input
                                id="top-search"
                                type="search"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Search textbooks, laptops, dorm gear..."
                                class="form-input min-h-10 pl-9 text-sm"
                            />
                        </div>
                    </form>
                @endauth

                <div class="flex items-center gap-1.5">
                    @auth
                        {{-- Right side, in order: Sell, My Activity grid menu,
                             Messages, Notifications, Avatar - matching
                             Facebook's layout. Every icon here is the same
                             44x44 round button style; the theme toggle lives
                             inside the avatar menu only, not duplicated here. --}}
                        <a href="{{ route('listings.create') }}" class="hidden h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-brand-700 text-white transition-colors hover:bg-brand-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 md:inline-flex" title="Sell item" aria-label="Sell item">
                            <x-app-icon name="plus" class="h-5 w-5" />
                        </a>

                        <div class="relative hidden md:block" x-data="{ gridMenuOpen: false }" x-on:click.outside="gridMenuOpen = false" x-on:keydown.escape="gridMenuOpen = false">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:hover:bg-slate-300"
                                x-on:click="gridMenuOpen = !gridMenuOpen"
                                x-bind:aria-expanded="gridMenuOpen"
                                aria-haspopup="true"
                                aria-label="My Activity menu"
                                title="My Activity"
                            >
                                <x-app-icon name="layout-grid" class="h-5 w-5" />
                            </button>

                            <div x-cloak x-show="gridMenuOpen" x-transition.opacity class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-200">
                                <a href="{{ route('activity.buying') }}" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="bag" class="h-5 w-5" /> Buying
                                </a>
                                <a href="{{ route('activity.index', ['tab' => 'selling']) }}" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="layout-grid" class="h-5 w-5" /> Selling
                                </a>
                                <a href="{{ route('activity.buying') }}#saved" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="bookmark" class="h-5 w-5" /> Saved
                                </a>
                                <a href="{{ route('activity.index', ['tab' => 'insights']) }}" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="chart-bar" class="h-5 w-5" /> Insights
                                </a>
                            </div>
                        </div>

                        <a
                            href="{{ route('chat.index') }}"
                            @class(['relative inline-flex h-11 w-11 items-center justify-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:hover:bg-slate-300', 'text-brand-800' => request()->routeIs('chat.*')])
                            @if(request()->routeIs('chat.*')) aria-current="page" @endif
                            aria-label="Messages"
                            title="Messages"
                        >
                            <x-app-icon name="chat" class="h-5 w-5" />
                            <span class="sr-only">Messages</span>
                            @if ($unreadMessageCount > 0)
                                <span class="absolute right-0.5 top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-accent-700 px-1 text-[10px] font-bold text-white dark:border-slate-200">
                                    {{ $unreadMessageCount > 9 ? '9+' : $unreadMessageCount }}<span class="sr-only"> unread</span>
                                </span>
                            @endif
                        </a>

                        @livewire('notifications.bell')

                        <div class="relative" x-data="{ menuOpen: false }" x-on:click.outside="menuOpen = false" x-on:keydown.escape="menuOpen = false">
                            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full transition-colors hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:hover:bg-slate-300" x-on:click="menuOpen = !menuOpen" x-bind:aria-expanded="menuOpen" aria-haspopup="true" aria-label="Your account" title="{{ auth()->user()->name }}">
                                <x-avatar :user="auth()->user()" class="h-8 w-8 text-xs" />
                                <span class="sr-only">{{ auth()->user()->name }}</span>
                            </button>

                            <div x-cloak x-show="menuOpen" x-transition.opacity class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-200">
                                <div class="flex items-center gap-2.5 border-b border-slate-100 px-3 pb-3 pt-2">
                                    <x-avatar :user="auth()->user()" class="h-9 w-9 flex-shrink-0 text-sm" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
                                        <p class="truncate text-xs text-slate-600">{{ auth()->user()->email }}</p>
                                        @if (auth()->user()->is_verified)
                                            <span class="badge badge-success mt-1"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Official Student</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('profiles.show', auth()->user()) }}" class="mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="user" class="h-5 w-5" /> View public profile
                                </a>
                                <a href="{{ route('account') }}" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="pencil" class="h-5 w-5" /> My account
                                </a>
                                <a href="{{ route('appeals.mine') }}" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="flag" class="h-5 w-5" /> My appeals
                                </a>
                                <button type="button" x-on:click="menuOpen = false; $dispatch('open-help-panel')" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-800 hover:bg-slate-100">
                                    <x-app-icon name="help-circle" class="h-5 w-5" /> Help
                                </button>
                                @livewire('account.theme-toggle')
                                <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 pt-1">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-100">
                                        <x-app-icon name="logout" class="h-5 w-5" /> Log out
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        {{-- Guests have no avatar menu to hold the theme
                             toggle, so it stays here as its own button
                             rather than disappearing for them entirely. --}}
                        <button
                            type="button"
                            class="hidden h-11 w-11 items-center justify-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:hover:bg-slate-300 md:inline-flex"
                            x-data
                            x-on:click="window.uniMarketTheme.toggle()"
                            aria-label="Toggle dark mode"
                            title="Toggle dark mode"
                        >
                            <x-app-icon name="sun" class="hidden h-5 w-5 dark:block" />
                            <x-app-icon name="moon" class="block h-5 w-5 dark:hidden" />
                        </button>
                        <a href="{{ route('login') }}" class="nav-link">Log in</a>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm hidden sm:inline-flex sm:min-h-11 sm:px-4 sm:text-sm">Join UniMarket</a>
                    @endauth

                    <button type="button" class="btn btn-secondary btn-sm min-h-11 min-w-11 px-2 md:hidden" x-on:click="mobileOpen = !mobileOpen" x-bind:aria-expanded="mobileOpen" aria-controls="mobile-menu" title="Toggle menu">
                        <span class="sr-only">Toggle menu</span>
                        <x-app-icon name="menu" x-show="!mobileOpen" />
                        <x-app-icon name="x" x-cloak x-show="mobileOpen" />
                    </button>
                </div>
            </div>
        </div>

        <nav id="mobile-menu" x-cloak x-show="mobileOpen" x-transition.opacity class="border-t border-slate-200 bg-white md:hidden dark:bg-slate-200" aria-label="Account menu">
            <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3 sm:px-6">
                <button
                    type="button"
                    class="nav-link flex w-full items-center gap-2 py-3 text-left"
                    x-data
                    x-on:click="window.uniMarketTheme.toggle()"
                >
                    <x-app-icon name="sun" class="hidden h-5 w-5 dark:block" />
                    <x-app-icon name="moon" class="block h-5 w-5 dark:hidden" />
                    <span>Toggle dark mode</span>
                </button>
                @auth
                    <button
                        type="button"
                        class="nav-link flex w-full items-center gap-2 py-3 text-left"
                        x-on:click="$dispatch('open-help-panel'); mobileOpen = false"
                    >
                        <x-app-icon name="help-circle" class="h-5 w-5" />
                        <span>Help</span>
                    </button>
                @endauth
                @auth
                    <div class="mt-2 border-t border-slate-100 pt-3">
                        <div class="flex items-center gap-2.5 px-3">
                            <x-avatar :user="auth()->user()" class="h-9 w-9 flex-shrink-0 text-sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-600">{{ auth()->user()->email }}</p>
                            </div>
                        </div>
                        <a href="{{ route('profiles.show', auth()->user()) }}" class="nav-link mt-1 flex items-center gap-2 py-3">
                            <x-app-icon name="user" class="h-5 w-5" /> View public profile
                        </a>
                        <a href="{{ route('account') }}" class="nav-link flex items-center gap-2 py-3">
                            <x-app-icon name="pencil" class="h-5 w-5" /> My account
                        </a>
                        <a href="{{ route('appeals.mine') }}" class="nav-link flex items-center gap-2 py-3">
                            <x-app-icon name="flag" class="h-5 w-5" /> My appeals
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
                    Confirm your email address before you can request your Verified Student badge.
                    <a href="{{ route('verification.notice') }}" class="font-semibold underline underline-offset-2">Verify now</a>
                </x-alert>
            </div>
        @elseif (auth()->user()->canSubmitStudentVerification() && ! request()->routeIs('verification.student.form'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-alert type="warning">
                    Submit your student ID document to earn the Verified Student badge.
                    <a href="{{ route('verification.student.form') }}" class="font-semibold underline underline-offset-2">Submit now</a>
                </x-alert>
            </div>
        @elseif (auth()->user()->student_verification_status === \App\Models\User::STUDENT_VERIFICATION_PENDING && ! request()->routeIs('verification.student.form'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-alert type="info">
                    Your student verification document is under review.
                    <a href="{{ route('verification.student.form') }}" class="font-semibold underline underline-offset-2">View status</a>
                </x-alert>
            </div>
        @endif

        @if (! auth()->user()->hasCompletedZutProfile() && ! request()->routeIs('account'))
            <div
                x-data="{ dismissed: false }"
                x-init="try { dismissed = localStorage.getItem('uniMarketZutBannerDismissed') === '1'; } catch (e) {}"
                x-show="! dismissed"
                class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8"
            >
                <x-alert type="info">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span>
                            Add the year you started and your school to your account - it only takes a moment, and helps show anonymous trends to students like you.
                            <a href="{{ route('account') }}" class="font-semibold underline underline-offset-2">Complete my profile</a>
                        </span>
                        <button
                            type="button"
                            x-on:click="dismissed = true; try { localStorage.setItem('uniMarketZutBannerDismissed', '1'); } catch (e) {}"
                            class="-m-1 flex-shrink-0 rounded p-1 hover:bg-black/5"
                            title="Dismiss"
                        >
                            <span class="sr-only">Dismiss</span>
                            <x-app-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
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

    <main id="main-content" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 pb-24 sm:px-6 sm:py-8 md:pb-8 lg:px-8">
        {{ $slot }}
    </main>

    <footer class="mt-auto border-t border-slate-200 bg-white py-6 dark:bg-slate-200">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 text-center text-sm text-slate-600 sm:px-6 md:flex-row md:items-center md:justify-between md:text-left lg:px-8">
            <p>&copy; {{ date('Y') }} UniMarket. The student marketplace for campus communities.</p>
            <ul class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 font-medium text-slate-700">
                <li class="flex items-center gap-1.5"><x-app-icon name="shield" class="h-4 w-4 text-accent-700" /> Verified students</li>
                <li>Escrow protection</li>
                <li>Moderated disputes</li>
                <li><a href="{{ route('how-it-works') }}" class="hover:text-brand-800 dark:hover:text-brand-300">How UniMarket works</a></li>
                <li><a href="{{ route('reputation.verify') }}" class="hover:text-brand-800 dark:hover:text-brand-300">Verify a reputation record</a></li>
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
        class="pointer-events-none fixed inset-x-0 bottom-20 z-50 mx-auto flex max-w-md flex-col gap-2 px-4 md:bottom-4"
        aria-live="polite"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div class="alert pointer-events-auto shadow-lg" x-bind:class="'alert-' + toast.type" x-bind:role="toast.type === 'error' ? 'alert' : 'status'">
                <div class="min-w-0 flex-1 break-words" x-text="toast.message"></div>
                <button type="button" class="-m-1 flex-shrink-0 rounded p-1 hover:bg-black/5" x-on:click="remove(toast.id)" title="Dismiss">
                    <span class="sr-only">Dismiss</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </template>
    </div>

    {{-- Mobile bottom tab bar. Desktop keeps the existing top-bar icon nav;
         this only ever shows below md, and <main>/body padding below
         accounts for its height so content is never hidden behind it.
         Guests get no bottom tab bar at all - every destination it links
         to is marketplace content, now behind login. --}}
    @auth
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden dark:border-slate-300 dark:bg-slate-200/95"
            aria-label="Primary navigation"
        >
            <div class="grid grid-cols-5 items-stretch">
                <a
                    href="{{ route('listings.index') }}"
                    @class(['flex min-h-14 flex-col items-center justify-center gap-0.5 text-slate-500', 'text-brand-700' => request()->routeIs('listings.index', 'listings.show')])
                    @if (request()->routeIs('listings.index')) aria-current="page" @endif
                    aria-label="Home"
                >
                    <x-app-icon name="home" class="h-5 w-5" />
                    <span class="text-[10px] font-medium">Home</span>
                </a>

                <a
                    href="{{ route('listings.index', ['search' => request('search')]) }}#search"
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-slate-500"
                    aria-label="Search listings"
                >
                    <x-app-icon name="search" class="h-5 w-5" />
                    <span class="text-[10px] font-medium">Search</span>
                </a>

                <div class="flex items-center justify-center">
                    <a
                        href="{{ route('listings.create') }}"
                        class="flex h-14 w-14 -translate-y-3 items-center justify-center rounded-full bg-brand-700 text-white shadow-lg ring-4 ring-white transition-colors hover:bg-brand-800 dark:ring-slate-100"
                        aria-label="Sell an item"
                        title="Sell an item"
                    >
                        <x-app-icon name="plus" class="h-6 w-6" />
                    </a>
                </div>

                <a
                    href="{{ route('activity.index') }}"
                    @class(['flex min-h-14 flex-col items-center justify-center gap-0.5 text-slate-500', 'text-brand-700' => request()->routeIs('activity.index', 'listings.mine', 'listings.edit', 'transactions.tracker')])
                    @if (request()->routeIs('activity.index')) aria-current="page" @endif
                    aria-label="My Activity"
                >
                    <x-app-icon name="layout-grid" class="h-5 w-5" />
                    <span class="text-[10px] font-medium">Activity</span>
                </a>

                <button
                    type="button"
                    x-on:click="mobileOpen = !mobileOpen"
                    x-bind:aria-expanded="mobileOpen"
                    aria-controls="mobile-menu"
                    @class(['flex min-h-14 flex-col items-center justify-center gap-0.5 text-slate-500', 'text-brand-700' => request()->routeIs('account', 'profiles.show', 'appeals.mine')])
                    aria-label="Profile menu"
                >
                    <x-avatar :user="auth()->user()" class="h-5 w-5 text-[9px]" />
                    <span class="text-[10px] font-medium">Profile</span>
                </button>
            </div>
        </nav>

        @livewire('help-panel')
    @endauth

    @livewireScripts
</body>
</html>
