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
            @php
                $sidebarItems = [
                    'overview' => ['Overview', 'chart-bar'],
                    'selling' => ['Selling', 'bag'],
                    'buying' => ['Buying', 'download'],
                    'customers' => ['Customers', 'user'],
                ];
                $totals = match ($insightsSidebarView) {
                    'selling' => $sellerInsights->totals($user, $insightsPeriod),
                    'buying' => $buyerInsights->totals($user, $insightsPeriod),
                    default => null,
                };
            @endphp

            <div class="flex flex-col gap-4 lg:flex-row">
                <nav class="no-scrollbar -mx-4 flex gap-1 overflow-x-auto px-4 sm:mx-0 sm:px-0 lg:w-20 lg:flex-col lg:overflow-visible" aria-label="Insights sections">
                    @foreach ($sidebarItems as $key => [$label, $icon])
                        <button
                            type="button"
                            wire:click="setInsightsSidebarView('{{ $key }}')"
                            @class([
                                'flex min-h-16 flex-shrink-0 flex-col items-center justify-center gap-1 rounded-lg px-3 py-2 text-xs font-medium transition-colors lg:w-full',
                                'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-brand-300' => $insightsSidebarView === $key,
                                'text-slate-600 hover:bg-slate-100 hover:text-ink' => $insightsSidebarView !== $key,
                            ])
                            aria-current="{{ $insightsSidebarView === $key ? 'true' : 'false' }}"
                        >
                            <x-app-icon :name="$icon" class="h-5 w-5" />
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>

                <div class="min-w-0 flex-1 space-y-6">
                    <div class="card flex flex-wrap items-center gap-3 p-4">
                        <div class="flex items-center gap-2">
                            <label for="insights-period" class="text-sm font-medium text-slate-700">Date range</label>
                            <select id="insights-period" wire:model.live="insightsPeriod" class="form-input min-h-10 w-auto py-2">
                                @foreach ($periodOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($selectedCategory)
                            <span class="chip chip-active">
                                {{ $selectedCategory }}
                                <button type="button" wire:click="resetChartFilters" class="ml-1" aria-label="Clear category filter" title="Clear category filter">
                                    <x-app-icon name="x" class="h-3.5 w-3.5" />
                                </button>
                            </span>
                        @endif
                    </div>

                    @if ($insightsSidebarView === 'overview')
                        @php
                            $buyerTotals = $buyerInsights->totals($user, $insightsPeriod);
                            $sellerTotals = $sellerInsights->totals($user, $insightsPeriod);
                        @endphp
                        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <x-stat-card label="Items bought" :number="$buyerTotals['total_items']" />
                            <x-stat-card label="Total spent" :number="$buyerTotals['total_spent']" prefix="K" :decimals="2" />
                            <x-stat-card label="Items sold" :number="$sellerTotals['items_sold']" />
                            <x-stat-card label="Total earned" :number="$sellerTotals['total_earned']" prefix="K" :decimals="2" />
                        </div>

                        <x-section title="Spending vs. earnings over time" description="Last 12 months - not affected by the date range above." class="card p-5">
                            <x-chart-js id="overview-trend" type="line" :data="$charts['overview-trend']" aria-label="Spending versus earnings over time" empty="Buy or sell something to see your trend here." />
                        </x-section>
                    @elseif ($insightsSidebarView === 'selling')
                        @php $priceChecks = $sellerInsights->priceCheck($user); @endphp
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-stat-card label="Items sold" :number="$totals['items_sold']" />
                            <x-stat-card label="Total earned" :number="$totals['total_earned']" prefix="K" :decimals="2" />
                            <x-stat-card label="Average rating" :value="$totals['average_rating'] ? number_format($totals['average_rating'], 1).' / 5.0' : '—'" />
                        </div>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <x-section class="card p-5">
                                <x-slot:title>
                                    <span class="inline-flex items-center gap-1">
                                        Items sold by category
                                        <x-info-tip text="Click a bar to filter the price-check table below to that category. The busiest category is highlighted." />
                                    </span>
                                </x-slot:title>
                                <x-chart-js id="sold-by-category" type="bar" :data="$charts['sold-by-category']" click-dimension="category" aria-label="Items sold by category" empty="Once you complete a sale, your category breakdown will appear here." />
                            </x-section>

                            <x-section title="Earnings over time" description="Last 12 months - not affected by the date range above." class="card p-5">
                                <x-chart-js id="earnings-over-time" type="line" :data="$charts['earnings-over-time']" aria-label="Earnings over time" empty="Once you complete a sale, your earnings trend will appear here." />
                            </x-section>
                        </div>

                        <x-section title="From view to sale" description="How your listings' visits turn into enquiries and sales." class="card p-5">
                            <x-chart-js id="funnel" type="bar" :data="$charts['funnel']" aria-label="View to sale funnel" empty="Once students start viewing your listings, your funnel will appear here." height="h-48" />
                        </x-section>

                        <x-section title="Price check on your active listings" description="How your prices compare to similar recent sales." class="card overflow-x-auto p-5">
                            @php
                                $filteredPriceChecks = $selectedCategory
                                    ? $priceChecks->filter(fn ($row) => $row['listing']->category->name === $selectedCategory)
                                    : $priceChecks;
                            @endphp
                            @if ($filteredPriceChecks->isEmpty())
                                <x-empty-state icon="bag" title="No active listings" description="List an item to see how your price compares to similar sales." />
                            @else
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-left text-xs text-slate-500">
                                            <th class="py-1.5 font-medium">Listing</th>
                                            <th class="py-1.5 text-right font-medium">Vs. typical price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($filteredPriceChecks as $row)
                                            <tr class="border-b border-slate-100">
                                                <td class="py-1.5 pr-3 text-slate-700">{{ $row['listing']->title }}</td>
                                                <td class="py-1.5 text-right">
                                                    @if ($row['median'] === null)
                                                        <span class="text-xs text-slate-500">Not enough comparable sales yet</span>
                                                    @else
                                                        <span @class(['font-semibold', 'text-warn-700' => $row['difference_percentage'] > 10, 'text-ink' => $row['difference_percentage'] <= 10])>
                                                            {{ $row['difference_percentage'] > 0 ? 'about '.abs($row['difference_percentage']).'% above typical' : 'about '.abs($row['difference_percentage']).'% below typical' }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </x-section>
                    @elseif ($insightsSidebarView === 'buying')
                        @php $popular = $buyerInsights->popularWithStudentsLikeYou($user); @endphp
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-stat-card label="Items bought" :number="$totals['total_items']" />
                            <x-stat-card label="Total spent" :number="$totals['total_spent']" prefix="K" :decimals="2" />
                            <x-stat-card label="Average per item" :number="$totals['average_spend']" prefix="K" :decimals="2" />
                        </div>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <x-section title="Spending by category" class="card p-5">
                                <x-chart-js id="spending-by-category" type="doughnut" :data="$charts['spending-by-category']" click-dimension="category" aria-label="Spending by category" empty="Once you complete a purchase, your spending breakdown will appear here." />
                            </x-section>

                            <x-section title="Spending over time" description="Last 12 months - not affected by the date range above." class="card p-5">
                                <x-chart-js id="spending-over-time" type="line" :data="$charts['spending-over-time']" aria-label="Spending over time" empty="Once you complete a purchase, your spending trend will appear here." />
                            </x-section>
                        </div>

                        <x-section class="card p-5">
                            <x-slot:title>
                                <span class="inline-flex items-center gap-1">
                                    Popular with {{ $popular['level'] }}
                                    <x-info-tip text="To protect students' privacy, this only ever appears once at least {{ config('insights.min_group_size') }} different students are included - never anyone's individual activity." />
                                </span>
                            </x-slot:title>
                            <x-chart-js id="popular-with-you" type="bar" :data="$charts['popular-with-you']" aria-label="Popular with students like you" empty="Once enough students complete purchases, trends will appear here." />
                        </x-section>
                    @else
                        @php $buyers = $sellerInsights->buyersByYearAndSchool($user); @endphp
                        @if ($buyers['suppressed'])
                            <x-section title="Who buys from me" class="card p-5">
                                <x-empty-state icon="info" title="Not enough data yet" description="Once enough different students have bought from you, a breakdown by year, school and gender will appear here." />
                            </x-section>
                        @else
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                                <x-section title="By school" class="card p-5">
                                    <x-chart-js id="buyers-by-school" type="bar" :data="$charts['buyers-by-school']" aria-label="Buyers by school" />
                                </x-section>
                                <x-section title="By year of study" class="card p-5">
                                    <x-chart-js id="buyers-by-year" type="bar" :data="$charts['buyers-by-year']" aria-label="Buyers by year of study" />
                                </x-section>
                                <x-section class="card p-5">
                                    <x-slot:title>
                                        <span class="inline-flex items-center gap-1">
                                            By gender
                                            <x-info-tip text="To protect students' privacy, this only ever appears once at least {{ config('insights.min_group_size') }} different students have bought from you." />
                                        </span>
                                    </x-slot:title>
                                    <x-chart-js id="buyers-by-gender" type="doughnut" :data="$charts['buyers-by-gender']" aria-label="Buyers by gender" />
                                </x-section>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
