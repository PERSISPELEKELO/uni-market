@php
    $buyerInsights = app(\App\Services\Insights\BuyerInsightsService::class);
    $sellerInsights = app(\App\Services\Insights\SellerInsightsService::class);
    $user = auth()->user();
@endphp

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">My Activity</h1>
        <p class="mt-1 text-sm text-slate-600">Everything you're buying, selling and your personal marketplace insights, in one place.</p>
    </div>

    <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto border-b border-slate-200 px-4 pb-0 sm:mx-0 sm:px-0" role="tablist" aria-label="My Activity sections">
        <button type="button" role="tab" aria-selected="{{ $tab === 'buying' ? 'true' : 'false' }}" wire:click="setTab('buying')" @class(['min-h-11 border-b-2 px-3 py-2 text-sm font-semibold', 'border-brand-700 text-brand-800 dark:text-brand-300' => $tab === 'buying', 'border-transparent text-slate-600 hover:text-ink' => $tab !== 'buying'])>
            Buying
        </button>
        <button type="button" role="tab" aria-selected="{{ $tab === 'selling' ? 'true' : 'false' }}" wire:click="setTab('selling')" @class(['min-h-11 border-b-2 px-3 py-2 text-sm font-semibold', 'border-brand-700 text-brand-800 dark:text-brand-300' => $tab === 'selling', 'border-transparent text-slate-600 hover:text-ink' => $tab !== 'selling'])>
            Selling
        </button>
        <button type="button" role="tab" aria-selected="{{ $tab === 'insights' ? 'true' : 'false' }}" wire:click="setTab('insights')" @class(['min-h-11 border-b-2 px-3 py-2 text-sm font-semibold', 'border-brand-700 text-brand-800 dark:text-brand-300' => $tab === 'insights', 'border-transparent text-slate-600 hover:text-ink' => $tab !== 'insights'])>
            Insights
        </button>
    </div>

    <div class="mt-6">
        @if ($tab === 'buying')
            @livewire('transactions.tracker', ['transaction' => $resolvedTransaction], key('tracker-'.($transaction ?? 'default')))
        @elseif ($tab === 'selling')
            <div class="space-y-6">
                @if ($reservationsReceived->isNotEmpty())
                    <section class="card space-y-4 p-5 sm:p-6" aria-labelledby="reservations-received-heading">
                        <h2 id="reservations-received-heading" class="text-sm font-semibold text-ink">Buyers interested in your listings</h2>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($reservationsReceived as $listing)
                                @foreach ($listing->activeReservations as $reservation)
                                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-ink">{{ $reservation->buyer->name }} wants <a href="{{ route('listings.show', $listing) }}" class="font-semibold text-brand-800 underline underline-offset-2 dark:text-brand-300">{{ $listing->title }}</a></p>
                                            <p class="text-xs text-slate-600">Reserved {{ $reservation->created_at->diffForHumans() }}</p>
                                        </div>
                                        <a href="{{ route('listings.show', $listing) }}" class="btn btn-primary btn-sm">Choose buyer</a>
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </section>
                @endif

                @livewire('marketplace.my-listings')
            </div>
        @elseif ($tab === 'insights')
            <div class="space-y-6">
                <div class="flex gap-2" role="tablist" aria-label="Insights view">
                    <button type="button" wire:click="setInsightsView('buyer')" @class(['chip', 'chip-active' => $insightsView === 'buyer'])>As a buyer</button>
                    <button type="button" wire:click="setInsightsView('seller')" @class(['chip', 'chip-active' => $insightsView === 'seller'])>As a seller</button>
                </div>

                @if ($insightsView === 'buyer')
                    @php $totals = $buyerInsights->totals($user); $popular = $buyerInsights->popularWithStudentsLikeYou($user); @endphp
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="card p-4"><p class="text-xs text-slate-600">Items bought</p><p class="mt-1 text-2xl font-bold text-ink">{{ $totals['total_items'] }}</p></div>
                        <div class="card p-4"><p class="text-xs text-slate-600">Total spent</p><p class="mt-1 text-2xl font-bold text-ink">K{{ number_format($totals['total_spent'], 2) }}</p></div>
                        <div class="card p-4"><p class="text-xs text-slate-600">Average per item</p><p class="mt-1 text-2xl font-bold text-ink">K{{ number_format($totals['average_spend'], 2) }}</p></div>
                    </div>

                    <div class="card space-y-3 p-5">
                        <h2 class="text-sm font-semibold text-ink">Spending by category</h2>
                        @forelse ($buyerInsights->spendingByCategory($user) as $row)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-700">{{ $row['category'] }}</span>
                                <span class="font-semibold text-ink">K{{ number_format($row['amount'], 2) }} ({{ $row['percentage'] }}%)</span>
                            </div>
                        @empty
                            <x-empty-state icon="bag" title="No purchases yet" description="Once you complete a purchase, your spending breakdown will appear here." />
                        @endforelse
                    </div>

                    <div class="card space-y-3 p-5">
                        <h2 class="text-sm font-semibold text-ink">Popular with {{ $popular['level'] }}</h2>
                        @forelse ($popular['categories'] as $row)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-700">{{ $row['name'] }}</span>
                                <span class="font-semibold text-ink">{{ $row['total'] }} {{ \Illuminate\Support\Str::plural('purchase', $row['total']) }}</span>
                            </div>
                        @empty
                            <x-empty-state icon="info" title="Not enough data yet" description="Once enough students complete purchases, trends will appear here." />
                        @endforelse
                    </div>
                @else
                    @php
                        $totals = $sellerInsights->totals($user);
                        $buyers = $sellerInsights->buyersByYearAndSchool($user);
                        $priceChecks = $sellerInsights->priceCheck($user);
                    @endphp
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="card p-4"><p class="text-xs text-slate-600">Items sold</p><p class="mt-1 text-2xl font-bold text-ink">{{ $totals['items_sold'] }}</p></div>
                        <div class="card p-4"><p class="text-xs text-slate-600">Total earned</p><p class="mt-1 text-2xl font-bold text-ink">K{{ number_format($totals['total_earned'], 2) }}</p></div>
                        <div class="card p-4"><p class="text-xs text-slate-600">Average rating</p><p class="mt-1 text-2xl font-bold text-ink">{{ $totals['average_rating'] ? number_format($totals['average_rating'], 1).' / 5.0' : '—' }}</p></div>
                    </div>

                    <div class="card space-y-3 p-5">
                        <h2 class="text-sm font-semibold text-ink">Who buys from me</h2>
                        @if ($buyers['suppressed'])
                            <x-empty-state icon="info" title="Not enough data yet" description="Once enough different students have bought from you, a breakdown by year and school will appear here." />
                        @else
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="mb-1 text-xs font-semibold uppercase text-slate-600">By year of study</p>
                                    @foreach ($buyers['by_year'] as $row)
                                        <div class="flex items-center justify-between text-sm"><span>Year {{ $row['year'] }}</span><span class="font-semibold">{{ $row['percentage'] }}%</span></div>
                                    @endforeach
                                </div>
                                <div>
                                    <p class="mb-1 text-xs font-semibold uppercase text-slate-600">By school</p>
                                    @foreach ($buyers['by_school'] as $row)
                                        <div class="flex items-center justify-between text-sm"><span>{{ $row['school'] }}</span><span class="font-semibold">{{ $row['percentage'] }}%</span></div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="card space-y-3 p-5">
                        <h2 class="text-sm font-semibold text-ink">Price check on your active listings</h2>
                        @forelse ($priceChecks as $row)
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate text-slate-700">{{ $row['listing']->title }}</span>
                                @if ($row['median'] === null)
                                    <span class="text-xs text-slate-500">Not enough comparable sales yet</span>
                                @else
                                    <span class="font-semibold {{ $row['difference_percentage'] > 10 ? 'text-warn-700' : 'text-ink' }}">
                                        {{ $row['difference_percentage'] > 0 ? 'about '.abs($row['difference_percentage']).'% above typical' : 'about '.abs($row['difference_percentage']).'% below typical' }}
                                    </span>
                                @endif
                            </div>
                        @empty
                            <x-empty-state icon="bag" title="No active listings" description="List an item to see how your price compares to similar sales." />
                        @endforelse
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
