<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#F8FAFC]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'UniMarket - Campus Student Marketplace' }}</title>

    <!-- Google Sans Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS / Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body, button, input, select, textarea {
            font-family: 'Google Sans', sans-serif !important;
        }
    </style>
</head>
<body class="h-full bg-[#F8FAFC] text-[#0F172A] antialiased flex flex-col min-h-screen">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Brand Logo & Main Nav -->
                <div class="flex items-center space-x-8">
                    <a href="{{ route('listings.index') }}" class="flex items-center space-x-2 text-[#1E293B] font-semibold text-lg tracking-tight">
                        <div class="w-9 h-9 rounded-lg bg-[#1E293B] flex items-center justify-center text-white shadow-sm font-semibold">
                            U
                        </div>
                        <span>UniMarket</span>
                    </a>

                    <nav class="hidden md:flex items-center space-x-6">
                        <a href="{{ route('listings.index') }}" class="text-sm font-medium text-[#0F172A] hover:text-[#312E81] transition">
                            Explore Marketplace
                        </a>
                        @auth
                            <a href="{{ route('chat.index') }}" class="text-sm font-medium text-[#0F172A] hover:text-[#312E81] transition flex items-center space-x-1.5">
                                <span>Messages</span>
                                @php
                                    $unreadCount = \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count();
                                @endphp
                                @if($unreadCount > 0)
                                    <span class="bg-[#059669] text-white text-xs px-2 py-0.5 rounded-full font-medium">
                                        {{ $unreadCount }}
                                    </span>
                                @endif
                            </a>
                            <a href="{{ route('transactions.tracker') }}" class="text-sm font-medium text-[#0F172A] hover:text-[#312E81] transition">
                                My Transactions
                            </a>
                        @endauth
                    </nav>
                </div>

                <!-- Right Action Buttons & Auth Status -->
                <div class="flex items-center space-x-4">
                    @auth
                        <!-- Student Verification Badge -->
                        <div class="hidden sm:flex items-center space-x-2 bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-lg text-xs">
                            <span class="text-[#0F172A] font-medium">{{ auth()->user()->name }}</span>
                            @if(auth()->user()->is_verified)
                                <span class="bg-[#059669]/10 text-[#059669] border border-[#059669]/20 font-medium px-2 py-0.5 rounded-full flex items-center space-x-1">
                                    <svg class="w-3 h-3 fill-current" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Official Student</span>
                                </span>
                            @endif
                        </div>

                        <!-- Post Listing Button -->
                        <a href="{{ route('listings.create') }}" class="bg-[#1E293B] hover:bg-[#312E81] text-white font-medium px-4 py-2 rounded-lg text-sm transition shadow-sm flex items-center space-x-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Sell Item</span>
                        </a>

                        <!-- Logout Form -->
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-slate-500 hover:text-slate-800 transition">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-[#1E293B] hover:text-[#312E81] transition">
                            Log In
                        </a>
                        <a href="{{ route('register') }}" class="bg-[#1E293B] hover:bg-[#312E81] text-white font-medium px-4 py-2 rounded-lg text-sm transition shadow-sm">
                            Join Campus Market
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Global Success / Warning Alerts -->
    @if(session()->has('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-[#059669]/10 border border-[#059669]/30 text-[#059669] px-4 py-3 rounded-lg text-sm font-medium flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-[#D97706]/10 border border-[#D97706]/30 text-[#D97706] px-4 py-3 rounded-lg text-sm font-medium flex items-center justify-between shadow-sm">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <!-- Clean Minimal Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center md:flex md:justify-between md:items-center">
            <p class="text-xs text-slate-500 font-regular">
                &copy; {{ date('Y') }} UniMarket. Verified Student Marketplace for Campus Communities.
            </p>
            <div class="flex justify-center space-x-6 mt-3 md:mt-0 text-xs font-medium text-slate-600">
                <span class="flex items-center space-x-1">
                    <span class="w-2 h-2 rounded-full bg-[#059669]"></span>
                    <span>Verified Domain Protection</span>
                </span>
                <span>Escrow Lifecycle</span>
                <span>AI Moderated Disputes</span>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
