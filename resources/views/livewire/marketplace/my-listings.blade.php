<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">My listings</h1>
            <p class="mt-1 text-sm text-slate-600">Manage the items you are selling.</p>
        </div>
        <a href="{{ route('listings.create') }}" class="btn btn-primary">
            <x-app-icon name="plus" class="h-4 w-4" /> Sell an item
        </a>
    </div>

    @if ($listings->count() > 0)
        <ul class="space-y-3">
            @foreach ($listings as $listing)
                <li wire:key="listing-{{ $listing->id }}" class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                    <a href="{{ route('listings.show', $listing) }}" class="block flex-shrink-0">
                        <x-listing-image :src="$listing->cover_image_url" :alt="$listing->title" class="h-40 w-full rounded-lg sm:h-20 sm:w-28" />
                    </a>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :status="$listing->status" />
                            <span class="text-xs text-slate-600">{{ $listing->category->name }}</span>
                        </div>
                        <h2 class="mt-1 break-words text-base font-semibold text-ink">
                            <a href="{{ route('listings.show', $listing) }}" class="hover:text-brand-800 dark:hover:text-brand-300">{{ $listing->title }}</a>
                        </h2>
                        <p class="text-sm font-bold text-ink">K{{ number_format($listing->price, 2) }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2 sm:flex-shrink-0">
                        @can('update', $listing)
                            <a href="{{ route('listings.edit', $listing) }}" class="btn btn-secondary btn-sm">
                                <x-app-icon name="pencil" class="h-4 w-4" /> Edit
                            </a>
                        @endcan
                        @can('delete', $listing)
                            <button
                                type="button"
                                wire:click="delete({{ $listing->id }})"
                                wire:confirm="Remove this listing? Buyers will no longer be able to see it."
                                class="btn btn-danger-outline btn-sm"
                            >
                                <x-app-icon name="trash" class="h-4 w-4" /> Remove
                            </button>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">
            {{ $listings->links() }}
        </div>
    @else
        <div class="card px-6 py-12 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                <x-app-icon name="bag" class="h-6 w-6" />
            </div>
            <h2 class="mt-3 text-base font-semibold text-ink">You have not listed anything yet</h2>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">Got a textbook, gadget or dorm item you no longer need? List it in a couple of minutes.</p>
            <a href="{{ route('listings.create') }}" class="btn btn-primary mt-4">Sell your first item</a>
        </div>
    @endif
</div>
