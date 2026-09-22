@php
    $isSelf = auth()->id() === $user->id;
@endphp

<div class="mx-auto max-w-2xl space-y-6">
    <a href="{{ route('listings.index') }}" class="inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-slate-700 hover:text-brand-800">
        <x-app-icon name="arrow-left" class="h-4 w-4" /> Back to marketplace
    </a>

    <div class="card p-5 sm:p-8">
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:text-left">
            <x-avatar :user="$user" class="h-20 w-20 flex-shrink-0 text-2xl" />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                    <h1 class="break-words text-xl font-bold text-ink">{{ $user->name }}</h1>
                    @if ($user->is_verified)
                        <span class="badge badge-success"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Official Student</span>
                    @endif
                </div>

                <div class="mt-1 flex items-center justify-center gap-1.5 sm:justify-start">
                    @if ($averageRating !== null)
                        <x-star-icon class="h-4 w-4 text-warn-500" />
                        <span class="text-sm font-semibold text-ink">{{ number_format($averageRating, 1) }}</span>
                        <span class="text-sm text-slate-600">({{ $ratingsCount }} {{ \Illuminate\Support\Str::plural('rating', $ratingsCount) }})</span>
                    @else
                        <span class="text-sm text-slate-600">No ratings yet</span>
                    @endif
                </div>

                <dl class="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm text-slate-600 sm:justify-start">
                    @if ($user->programme)
                        <div class="flex items-center gap-1">
                            <dt class="sr-only">Programme</dt>
                            <dd>{{ $user->programme }}</dd>
                        </div>
                    @endif
                    @if ($user->business_type)
                        <div class="flex items-center gap-1">
                            <dt class="sr-only">Business type</dt>
                            <dd class="badge badge-brand">{{ $user->business_type }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="sr-only">Member since</dt>
                        <dd>Member since {{ $user->created_at->format('M Y') }}</dd>
                    </div>
                </dl>

                @if (! $isSelf)
                    <button type="button" wire:click="message" class="btn btn-primary btn-sm mt-4">
                        <x-app-icon name="chat" class="h-4 w-4" /> {{ auth()->check() ? 'Message' : 'Log in to message' }}
                    </button>
                @endif
            </div>
        </div>

        @if ($user->bio)
            <div class="mt-6 border-t border-slate-100 pt-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-600">About</h2>
                <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-800">{{ $user->bio }}</p>
            </div>
        @endif
    </div>

    @if ($ratingsCount > 0)
        <div class="card space-y-5 p-5 sm:p-6" aria-labelledby="ratings-heading">
            <h2 id="ratings-heading" class="text-sm font-semibold text-ink">Ratings &amp; reviews</h2>

            <div class="space-y-1.5">
                @foreach ($ratingBreakdown as $stars => $count)
                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="w-10 flex-shrink-0">{{ $stars }} star</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-warn-500" style="width: {{ $ratingsCount > 0 ? round($count / $ratingsCount * 100) : 0 }}%"></div>
                        </div>
                        <span class="w-6 flex-shrink-0 text-right">{{ $count }}</span>
                    </div>
                @endforeach
            </div>

            @if ($recentRatings->isNotEmpty())
                <ul class="divide-y divide-slate-100 border-t border-slate-100 pt-3">
                    @foreach ($recentRatings as $rating)
                        <li class="flex items-start gap-3 py-3">
                            <x-avatar :user="$rating->rater" class="h-8 w-8 flex-shrink-0 text-xs" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-1">
                                    <span class="truncate text-sm font-medium text-ink">{{ $rating->rater->name }}</span>
                                    <span class="flex items-center gap-0.5 text-xs text-slate-600">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <x-star-icon :filled="$i <= $rating->stars" class="h-3.5 w-3.5 {{ $i <= $rating->stars ? 'text-warn-500' : 'text-slate-300' }}" />
                                        @endfor
                                    </span>
                                </div>
                                @if ($rating->comment)
                                    <p class="mt-0.5 whitespace-pre-line break-words text-sm text-slate-700">{{ $rating->comment }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-slate-500">{{ $rating->created_at->diffForHumans() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($activeListingsCount > 0)
        <div class="card p-5 text-center sm:p-6">
            <p class="text-sm text-slate-700">
                <span class="font-semibold text-ink">{{ $user->name }}</span> has
                <a href="{{ route('listings.index') }}" class="font-semibold text-brand-800 underline underline-offset-2">{{ $activeListingsCount }} {{ \Illuminate\Support\Str::plural('item', $activeListingsCount) }}</a>
                for sale right now.
            </p>
        </div>
    @endif
</div>
