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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex gap-2" role="tablist" aria-label="Insights view">
                        <button type="button" wire:click="setInsightsView('buyer')" @class(['chip', 'chip-active' => $insightsView === 'buyer'])>As a buyer</button>
                        <button type="button" wire:click="setInsightsView('seller')" @class(['chip', 'chip-active' => $insightsView === 'seller'])>As a seller</button>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="insights-period" class="text-sm font-medium text-slate-700">Date range</label>
                        <select id="insights-period" wire:model.live="insightsPeriod" class="form-input min-h-10 w-auto py-2">
                            @foreach ($periodOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($insightsView === 'buyer')
                    @php
                        $totals = $buyerInsights->totals($user, $insightsPeriod);
                        $popular = $buyerInsights->popularWithStudentsLikeYou($user);
                        $spendingData = collect($buyerInsights->spendingByCategory($user, $insightsPeriod))->map(fn ($row) => ['label' => $row['category'], 'value' => $row['amount']])->all();
                        $popularData = collect($popular['categories'])->map(fn ($row) => ['label' => $row['name'], 'value' => $row['total']])->all();
                        $spendingByMonth = collect($buyerInsights->spendingByMonth($user))->map(fn ($row) => ['label' => $row['month'], 'value' => $row['total']])->all();
                    @endphp
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-stat-card label="Items bought" :value="$totals['total_items']" />
                        <x-stat-card label="Total spent" value="K{{ number_format($totals['total_spent'], 2) }}" />
                        <x-stat-card label="Average per item" value="K{{ number_format($totals['average_spend'], 2) }}" />
                    </div>

                    <x-section title="Spending over time" description="Last 12 months - not affected by the date range above." class="card p-5">
                        @if (collect($spendingByMonth)->sum('value') <= 0)
                            <x-empty-state icon="bag" title="No purchases yet" description="Once you complete a purchase, your spending trend will appear here." />
                        @else
                            <x-chart :data="$spendingByMonth" value-label="Amount (K)" label-heading="Month" prefix="K" />
                        @endif
                    </x-section>

                    <x-section title="Spending by category" class="card p-5">
                        @if (empty($spendingData))
                            <x-empty-state icon="bag" title="No purchases yet" description="Once you complete a purchase, your spending breakdown will appear here." />
                        @else
                            <x-chart :data="$spendingData" value-label="Amount (K)" label-heading="Category" prefix="K" />
                        @endif
                    </x-section>

                    <x-section class="card p-5">
                        <x-slot:title>
                            <span class="inline-flex items-center gap-1">
                                Popular with {{ $popular['level'] }}
                                <x-info-tip text="To protect students' privacy, this only ever appears once at least {{ config('insights.min_group_size') }} different students are included - never anyone's individual activity." />
                            </span>
                        </x-slot:title>
                        @if (empty($popularData))
                            <x-empty-state icon="info" title="Not enough data yet" description="Once enough students complete purchases, trends will appear here." />
                        @else
                            <x-chart :data="$popularData" value-label="Purchases" label-heading="Category" />
                        @endif
                    </x-section>
                @else
                    @php
                        $totals = $sellerInsights->totals($user, $insightsPeriod);
                        $buyers = $sellerInsights->buyersByYearAndSchool($user);
                        $priceChecks = $sellerInsights->priceCheck($user);
                        $soldByCategory = $sellerInsights->soldByCategory($user, $insightsPeriod);
                        $funnel = $sellerInsights->funnel($user, $insightsPeriod);
                        $earningsByMonth = collect($sellerInsights->earningsByMonth($user))->map(fn ($row) => ['label' => $row['month'], 'value' => $row['total']])->all();
                        $byYearData = $buyers['suppressed'] ? [] : collect($buyers['by_year'])->map(fn ($row) => ['label' => 'Year '.$row['year'], 'value' => $row['percentage']])->all();
                        $bySchoolData = $buyers['suppressed'] ? [] : collect($buyers['by_school'])->map(fn ($row) => ['label' => $row['school'], 'value' => $row['percentage']])->all();
                        $byGenderData = $buyers['suppressed'] ? [] : collect($buyers['by_gender'])->map(fn ($row) => ['label' => $row['gender'], 'value' => $row['percentage']])->all();
                        $soldByCategoryData = collect($soldByCategory)->map(fn ($row) => ['label' => $row['category'].($row['is_trending'] ? ' 🔥' : ''), 'value' => $row['total_items']])->all();
                    @endphp
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-stat-card label="Items sold" :value="$totals['items_sold']" />
                        <x-stat-card label="Total earned" value="K{{ number_format($totals['total_earned'], 2) }}" />
                        <x-stat-card label="Average rating" :value="$totals['average_rating'] ? number_format($totals['average_rating'], 1).' / 5.0' : '—'" />
                    </div>

                    <x-section title="Earnings over time" description="Last 12 months - not affected by the date range above." class="card p-5">
                        @if (collect($earningsByMonth)->sum('value') <= 0)
                            <x-empty-state icon="bag" title="No sales yet" description="Once you complete a sale, your earnings trend will appear here." />
                        @else
                            <x-chart :data="$earningsByMonth" value-label="Amount (K)" label-heading="Month" prefix="K" />
                        @endif
                    </x-section>

                    <x-section class="card p-5">
                        <x-slot:title>
                            <span class="inline-flex items-center gap-1">
                                Items sold by category
                                <x-info-tip text="The category with the most items sold is marked 🔥." />
                            </span>
                        </x-slot:title>
                        @if (empty($soldByCategoryData))
                            <x-empty-state icon="bag" title="No sales yet" description="Once you complete a sale, your category breakdown will appear here." />
                        @else
                            <x-chart :data="$soldByCategoryData" value-label="Items sold" label-heading="Category" />
                        @endif
                    </x-section>

                    <x-section title="From view to sale" description="How your listings' visits turn into enquiries and sales." class="card p-5">
                        @if ($funnel['views'] === 0)
                            <x-empty-state icon="info" title="No views yet" description="Once students start viewing your listings, your funnel will appear here." />
                        @else
                            <x-chart :data="[
                                ['label' => 'Views', 'value' => $funnel['views']],
                                ['label' => 'Enquiries', 'value' => $funnel['enquiries']],
                                ['label' => 'Sales', 'value' => $funnel['sales']],
                            ]" value-label="Count" label-heading="Stage" />
                        @endif
                    </x-section>

                    <x-section class="card p-5">
                        <x-slot:title>
                            <span class="inline-flex items-center gap-1">
                                Who buys from me
                                <x-info-tip text="To protect students' privacy, this only ever appears once at least {{ config('insights.min_group_size') }} different students have bought from you." />
                            </span>
                        </x-slot:title>
                        @if ($buyers['suppressed'])
                            <x-empty-state icon="info" title="Not enough data yet" description="Once enough different students have bought from you, a breakdown by year, school and gender will appear here." />
                        @else
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <x-chart :data="$byYearData" value-label="Percent" label-heading="Year of study" suffix="%" />
                                <x-chart :data="$bySchoolData" value-label="Percent" label-heading="School" suffix="%" />
                                <x-chart :data="$byGenderData" value-label="Percent" label-heading="Gender" suffix="%" />
                            </div>
                        @endif
                    </x-section>

                    <x-section title="Price check on your active listings" description="How your prices compare to similar recent sales." class="card p-5">
                        @forelse ($priceChecks as $row)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="truncate text-slate-700">{{ $row['listing']->title }}</span>
                                @if ($row['median'] === null)
                                    <span class="flex-shrink-0 text-xs text-slate-500">Not enough comparable sales yet</span>
                                @else
                                    <span @class(['flex-shrink-0 font-semibold', 'text-warn-700' => $row['difference_percentage'] > 10, 'text-ink' => $row['difference_percentage'] <= 10])>
                                        {{ $row['difference_percentage'] > 0 ? 'about '.abs($row['difference_percentage']).'% above typical' : 'about '.abs($row['difference_percentage']).'% below typical' }}
                                    </span>
                                @endif
                            </div>
                        @empty
                            <x-empty-state icon="bag" title="No active listings" description="List an item to see how your price compares to similar sales." />
                        @endforelse
                    </x-section>
                @endif
            </div>
        @endif
    </div>
</div>
