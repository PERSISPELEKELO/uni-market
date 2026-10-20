<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-initial-theme="">
<head>
    <meta charset="utf-8">
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('uniMarketTheme'); } catch (e) {}
            var theme = stored || 'auto';
            var mql = window.matchMedia('(prefers-color-scheme: dark)');
            function apply() {
                document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'auto' && mql.matches));
            }
            apply();
            mql.addEventListener('change', function () { if (theme === 'auto') { apply(); } });
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
<body class="flex min-h-screen flex-col bg-white dark:bg-slate-100">

    <a href="#main-content" class="sr-only z-50 rounded-lg bg-white px-4 py-2 font-semibold text-brand-800 dark:bg-slate-200 dark:text-brand-300 shadow focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Skip to main content
    </a>

    @foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'status' => 'info'] as $flashKey => $flashType)
        @if (session()->has($flashKey))
            <div class="mx-auto mt-4 w-full max-w-5xl px-4">
                <x-alert :type="$flashType">{{ session($flashKey) }}</x-alert>
            </div>
        @endif
    @endforeach

    <main id="main-content" class="mx-auto w-full max-w-5xl flex-1 px-4 sm:px-6">
        {{ $slot }}
    </main>

    <footer class="mt-auto py-6 text-center text-xs text-slate-500">
        <a href="{{ route('how-it-works') }}" class="font-medium hover:text-brand-800 dark:hover:text-brand-300">How UniMarket works</a>
        <span class="mx-2">&middot;</span>
        &copy; {{ date('Y') }} UniMarket &middot; Zambia University College of Technology
    </footer>

    {{-- Notifications dispatched from Livewire components --}}
    <div
        x-data="{
            toasts: [],
            add(detail) {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, type: detail.type ?? 'success', message: detail.message });
                setTimeout(() => this.remove(id), 7000);
            },
            remove(id) { this.toasts = this.toasts.filter((toast) => toast.id !== id); },
        }"
        x-on:notify.window="add($event.detail)"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 mx-auto flex max-w-md flex-col gap-2 px-4"
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

    @livewireScripts
</body>
</html>
