@props([])

<div {{ $attributes->class(['card flex flex-col overflow-hidden']) }} aria-hidden="true">
    <x-skeleton class="aspect-[4/3] w-full rounded-none" />
    <div class="flex flex-1 flex-col gap-2 p-4 sm:p-5">
        <x-skeleton class="h-3 w-20" />
        <x-skeleton class="h-4 w-4/5" />
        <x-skeleton class="mt-2 h-5 w-24" />
    </div>
    <div class="flex items-center gap-2 border-t border-slate-100 px-4 py-3 sm:px-5">
        <x-skeleton class="h-5 w-5 rounded-full" />
        <x-skeleton class="h-3 w-24" />
    </div>
</div>
