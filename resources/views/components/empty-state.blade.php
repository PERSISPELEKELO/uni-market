@props(['icon' => 'info', 'title', 'description' => null, 'actionLabel' => null, 'actionUrl' => null])

<div {{ $attributes->class(['rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center dark:bg-transparent']) }}>
    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-500/10">
        <x-app-icon :name="$icon" class="h-5 w-5" />
    </div>
    <h3 class="mt-3 text-sm font-semibold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-primary btn-sm mt-4">{{ $actionLabel }}</a>
    @endif
    {{ $slot }}
</div>
