{{-- Shared body for the friendly error pages. Expects $code, $heading and $message. --}}
<div class="mx-auto flex max-w-lg flex-col items-center py-12 text-center sm:py-20">
    <p class="text-sm font-semibold text-brand-800 dark:text-brand-300">Error {{ $code }}</p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ $heading }}</h1>
    <p class="mt-3 text-base text-slate-600">{{ $message }}</p>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <a href="{{ route('listings.index') }}" class="btn btn-primary">Back to the marketplace</a>
        @guest
            <a href="{{ route('login') }}" class="btn btn-secondary">Log in</a>
        @endguest
    </div>
</div>
