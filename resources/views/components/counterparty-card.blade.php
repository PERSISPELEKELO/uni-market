@props(['user', 'role', 'transactionId' => null, 'listingId' => null])

@php
    $average = $user->averageRating();
    $ratingsCount = $user->ratingsCount();
    $count = $role === 'Seller' ? $user->completedSalesCount() : $user->completedPurchasesCount();
    $messageParams = array_filter(['receiver' => $user->id, 'listing' => $listingId, 'transaction' => $transactionId]);
@endphp

<div {{ $attributes->class(['flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3']) }}>
    <a href="{{ route('profiles.show', $user) }}" class="flex min-w-0 flex-1 items-center gap-3 hover:opacity-80">
        <x-avatar :user="$user" class="h-11 w-11 flex-shrink-0 text-base" />
        <div class="min-w-0">
            <span class="flex flex-wrap items-center gap-1.5">
                <span class="truncate text-sm font-semibold text-ink">{{ $user->name }}</span>
                @if ($user->is_verified)
                    <span class="badge badge-success flex-shrink-0 px-1.5 py-0 text-[10px]"><x-app-icon name="check-circle" class="h-3 w-3" /> Verified</span>
                @endif
                <span class="badge badge-neutral flex-shrink-0 px-1.5 py-0 text-[10px]">{{ $role }}</span>
            </span>
            <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-600">
                @if ($average !== null)
                    <span class="flex items-center gap-1"><x-star-icon class="h-3.5 w-3.5 text-warn-500" /> {{ number_format($average, 1) }} &middot; {{ $ratingsCount }} {{ \Illuminate\Support\Str::plural('rating', $ratingsCount) }}</span>
                @else
                    <span>No ratings yet</span>
                @endif
                @if ($count > 0)
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ $count }} completed {{ $role === 'Seller' ? \Illuminate\Support\Str::plural('sale', $count) : \Illuminate\Support\Str::plural('purchase', $count) }}</span>
                @endif
                <span aria-hidden="true">&middot;</span>
                <span>Member since {{ $user->created_at->format('M Y') }}</span>
            </p>
        </div>
    </a>

    @unless (auth()->id() === $user->id)
        <div class="flex flex-shrink-0 gap-2">
            <a href="{{ route('profiles.show', $user) }}" class="btn btn-secondary btn-sm">View profile</a>
            <a href="{{ route('chat.thread', $messageParams) }}" class="btn btn-primary btn-sm">
                <x-app-icon name="chat" class="h-4 w-4" /> Message
            </a>
        </div>
    @endunless
</div>
