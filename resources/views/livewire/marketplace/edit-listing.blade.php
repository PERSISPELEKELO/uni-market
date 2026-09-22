<div class="mx-auto max-w-3xl">
    <a href="{{ route('listings.mine') }}" class="mb-4 inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand-800 dark:hover:text-brand-300">
        <x-app-icon name="arrow-left" class="h-4 w-4" /> Back to my listings
    </a>

    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Edit listing</h1>
        <p class="mt-1 text-sm text-slate-600">Update the details of your item. Changes are visible to buyers straight away.</p>
    </div>

    <form wire:submit="save" novalidate class="card space-y-6 p-4 sm:p-8">
        @if ($errors->any())
            <x-alert type="error">Please fix the highlighted fields below and try again.</x-alert>
        @endif

        @include('livewire.marketplace.partials.listing-fields')

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('listings.mine') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,form.images">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
</div>
