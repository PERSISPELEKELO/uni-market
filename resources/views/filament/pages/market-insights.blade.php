@php
    $sections = [
        'overview' => ['Overview', 'heroicon-o-squares-2x2'],
        'marketplace' => ['Marketplace', 'heroicon-o-building-storefront'],
        'growth' => ['Growth', 'heroicon-o-arrow-trending-up'],
        'trust' => ['Trust & Safety', 'heroicon-o-shield-check'],
        'listings' => ['Listings', 'heroicon-o-rectangle-stack'],
    ];

    $totals = $this->totals();
    $trust = $this->trust();
    $funnel = $this->listingFunnel();
    $fastest = $this->fastestSelling();
    $peakTimes = $this->peakTimes();
    $yearHeatmap = $this->buyersByYearAndCategory();
    $schoolHeatmap = $this->buyersBySchoolAndCategory();
    $genderHeatmap = $this->buyersByGenderAndCategory();
    $shape = $this->sellersVsBuyersVsBoth();
    $topListings = $this->topListings();
    $charts = $this->chartsForCurrentSection();
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-6 lg:flex-row">
        <nav class="flex gap-1 overflow-x-auto lg:w-44 lg:flex-shrink-0 lg:flex-col lg:overflow-visible" aria-label="Market Insights sections">
            @foreach ($sections as $key => [$label, $icon])
                <button
                    type="button"
                    wire:click="setSection('{{ $key }}')"
                    @class([
                        'flex min-h-11 flex-shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors lg:w-full',
                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $section === $key,
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' => $section !== $key,
                    ])
                    aria-current="{{ $section === $key ? 'true' : 'false' }}"
                >
                    <x-filament::icon :icon="$icon" class="h-5 w-5" />
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        <div class="min-w-0 flex-1 space-y-6">
            <x-filament::section>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div>
                            <label for="period" class="text-sm font-medium text-gray-700 dark:text-gray-300">Period</label>
                            <select id="period" wire:model.live="period" class="fi-select-input mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700">
                                @foreach ($this->periodOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($selectedCategory)
                            <x-filament::badge color="primary" class="mt-5">
                                {{ $selectedCategory }}
                                <button type="button" wire:click="resetChartFilters" class="ml-1 align-middle" title="Clear category filter">&times;</button>
                            </x-filament::badge>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Breakdowns naming a group of students only appear once at least {{ config('insights.min_group_size') }} distinct students are included.
                    </p>
                </div>
            </x-filament::section>

            @if ($section === 'overview')
                <x-filament::section>
                    <x-slot name="heading">Marketplace totals</x-slot>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ([
                            ['label' => 'Active listings', 'value' => number_format($totals['active_listings']), 'change' => null],
                            ['label' => 'Completed transactions', 'value' => number_format($totals['completed_transactions']), 'change' => $totals['change']['transactions']],
                            ['label' => 'Total value (K)', 'value' => number_format($totals['total_value'], 2), 'change' => $totals['change']['value']],
                            ['label' => 'Active buyers', 'value' => number_format($totals['active_buyers']), 'change' => $totals['change']['buyers']],
                            ['label' => 'Active sellers', 'value' => number_format($totals['active_sellers']), 'change' => $totals['change']['sellers']],
                        ] as $stat)
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                                @if ($stat['change'] !== null)
                                    <x-filament::badge :color="$stat['change'] > 0 ? 'success' : ($stat['change'] < 0 ? 'danger' : 'gray')" class="mt-1">
                                        {{ $stat['change'] > 0 ? '+' : '' }}{{ $stat['change'] }}% vs previous period
                                    </x-filament::badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Value over time (weekly)</x-slot>
                    <x-chart-js id="value-over-time" type="line" :data="$charts['value-over-time']" aria-label="Transaction value over time" />
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Top listings by sale value</x-slot>

                    @if (empty($topListings))
                        <p class="text-sm text-gray-500 dark:text-gray-400">No completed sales in this period.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        <th class="py-1.5 pr-4 font-medium">Listing</th>
                                        <th class="py-1.5 font-medium">Amount (K)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($topListings as $row)
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="py-1.5 pr-4">{{ $row['listing']->title }}</td>
                                            <td class="py-1.5 font-medium">{{ number_format($row['amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            @elseif ($section === 'marketplace')
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <x-filament::section>
                        <x-slot name="heading">Buyers by year of study &times; category</x-slot>
                        <x-slot name="description">Cells with fewer than {{ config('insights.min_group_size') }} distinct buyers are blank, not zero.</x-slot>

                        @if (empty($yearHeatmap['years']))
                            <p class="text-sm text-gray-500 dark:text-gray-400">Not enough data yet.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-1 pr-2 text-left font-medium text-gray-500 dark:text-gray-400">Year</th>
                                            @foreach ($yearHeatmap['categories'] as $category)
                                                <th class="whitespace-nowrap px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">{{ $category }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($yearHeatmap['years'] as $i => $year)
                                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                                <td class="py-1 pr-2 font-medium">Year {{ $year }}</td>
                                                @foreach ($yearHeatmap['categories'] as $j => $category)
                                                    <td class="px-2 py-1 text-center">{{ $yearHeatmap['cells'][$i][$j] ?? '-' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">Buyers by school &times; category</x-slot>
                        <x-slot name="description">Cells with fewer than {{ config('insights.min_group_size') }} distinct buyers are blank, not zero.</x-slot>

                        @if (empty($schoolHeatmap['schools']))
                            <p class="text-sm text-gray-500 dark:text-gray-400">Not enough data yet.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-1 pr-2 text-left font-medium text-gray-500 dark:text-gray-400">School</th>
                                            @foreach ($schoolHeatmap['categories'] as $category)
                                                <th class="whitespace-nowrap px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">{{ $category }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($schoolHeatmap['schools'] as $i => $school)
                                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                                <td class="py-1 pr-2 font-medium">{{ $school }}</td>
                                                @foreach ($schoolHeatmap['categories'] as $j => $category)
                                                    <td class="px-2 py-1 text-center">{{ $schoolHeatmap['cells'][$i][$j] ?? '-' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">Buyers by gender &times; category</x-slot>
                        <x-slot name="description">Cells with fewer than {{ config('insights.min_group_size') }} distinct buyers are blank, not zero.</x-slot>

                        @if (empty($genderHeatmap['genders']))
                            <p class="text-sm text-gray-500 dark:text-gray-400">Not enough data yet.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-1 pr-2 text-left font-medium text-gray-500 dark:text-gray-400">Gender</th>
                                            @foreach ($genderHeatmap['categories'] as $category)
                                                <th class="whitespace-nowrap px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">{{ $category }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($genderHeatmap['genders'] as $i => $gender)
                                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                                <td class="py-1 pr-2 font-medium">{{ $gender }}</td>
                                                @foreach ($genderHeatmap['categories'] as $j => $category)
                                                    <td class="px-2 py-1 text-center">{{ $genderHeatmap['cells'][$i][$j] ?? '-' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-filament::section>
                </div>

                <x-filament::section>
                    <x-slot name="heading">Supply vs. demand by category</x-slot>
                    <x-slot name="description">Active listings vs. reservations and searches - a gap suggests unmet demand or oversupply.</x-slot>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    <th class="py-1.5 pr-2 font-medium">Category</th>
                                    <th class="py-1.5 pr-2 font-medium">Listings</th>
                                    <th class="py-1.5 pr-2 font-medium">Reservations</th>
                                    <th class="py-1.5 font-medium">Searches</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->supplyVsDemand() as $row)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-1.5 pr-2">{{ $row['category'] }}</td>
                                        <td class="py-1.5 pr-2">{{ $row['active_listings'] }}</td>
                                        <td class="py-1.5 pr-2">{{ $row['reservations'] }}</td>
                                        <td class="py-1.5">{{ $row['searches'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Searches with little or no result ("unmet demand")</x-slot>

                    @php($unmet = $this->unmetSearches())
                    @if (empty($unmet))
                        <p class="text-sm text-gray-500 dark:text-gray-400">No consistently poor-result searches in the last 60 days.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        <th class="py-1.5 pr-2 font-medium">Search term</th>
                                        <th class="py-1.5 pr-2 font-medium">Times searched</th>
                                        <th class="py-1.5 font-medium">Average results</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($unmet as $row)
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="py-1.5 pr-2">{{ $row['term'] }}</td>
                                            <td class="py-1.5 pr-2">{{ $row['searches'] }}</td>
                                            <td class="py-1.5">{{ $row['average_results'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Frequently bought together (by category)</x-slot>
                    <x-slot name="description">Only pairs bought by at least {{ config('insights.min_group_size') }} distinct students are shown.</x-slot>

                    @php($pairs = $this->frequentlyBoughtTogether())
                    @if (empty($pairs))
                        <p class="text-sm text-gray-500 dark:text-gray-400">Not enough overlapping purchases yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        <th class="py-1.5 pr-2 font-medium">Category A</th>
                                        <th class="py-1.5 pr-2 font-medium">Category B</th>
                                        <th class="py-1.5 pr-2 font-medium">Confidence</th>
                                        <th class="py-1.5 pr-2 font-medium">Lift</th>
                                        <th class="py-1.5 font-medium">Buyers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pairs as $pair)
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="py-1.5 pr-2">{{ $pair['category_a'] }}</td>
                                            <td class="py-1.5 pr-2">{{ $pair['category_b'] }}</td>
                                            <td class="py-1.5 pr-2">{{ $pair['confidence'] }}%</td>
                                            <td class="py-1.5 pr-2">{{ $pair['lift'] }}</td>
                                            <td class="py-1.5">{{ $pair['buyers'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            @elseif ($section === 'growth')
                <x-filament::section>
                    <x-slot name="heading">User growth (new accounts per week)</x-slot>
                    <x-chart-js id="user-growth" type="line" :data="$charts['user-growth']" aria-label="New accounts per week" />
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Buyers vs. sellers vs. both</x-slot>
                    <x-slot name="description">Among everyone with at least one completed transaction in this period.</x-slot>
                    <x-chart-js id="buyer-seller-shape" type="doughnut" :data="$charts['buyer-seller-shape']" aria-label="Buyers versus sellers versus both" height="h-56" />
                </x-filament::section>
            @elseif ($section === 'trust')
                <x-filament::section>
                    <x-slot name="heading">Trust &amp; safety metrics</x-slot>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['label' => 'Dispute rate', 'value' => $trust['dispute_rate'].'%'],
                            ['label' => 'Average resolution time', 'value' => $trust['average_resolution_days'] !== null ? $trust['average_resolution_days'].' days' : '—'],
                            ['label' => 'Verification approval rate', 'value' => $trust['verification_approval_rate'] !== null ? $trust['verification_approval_rate'].'%' : '—'],
                            ['label' => 'Average seller rating', 'value' => $trust['average_seller_rating'] !== null ? $trust['average_seller_rating'].' / 5.0' : '—'],
                        ] as $stat)
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($trust['ai_agreement_rate'] !== null)
                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            AI-suggested resolution agreed with the admin's final decision in {{ $trust['ai_agreement_rate'] }}% of resolved, AI-analysed disputes in this period.
                        </p>
                    @endif
                </x-filament::section>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-filament::section>
                        <x-slot name="heading">Dispute rate by category</x-slot>
                        <x-slot name="description">Click a bar to filter the fastest-selling categories list alongside it.</x-slot>
                        <x-chart-js id="dispute-rate-by-category" type="bar" :data="$charts['dispute-rate-by-category']" click-dimension="category" aria-label="Dispute rate by category" empty="No disputes in this period." />
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">Fastest-selling categories</x-slot>
                        <x-chart-js id="fastest-selling" type="bar" :data="$charts['fastest-selling']" aria-label="Fastest selling categories by median days" empty="Not enough completed sales yet." />
                    </x-filament::section>
                </div>
            @else
                <x-filament::section>
                    <x-slot name="heading">Listing funnel</x-slot>
                    <x-chart-js id="funnel" type="bar" :data="$charts['funnel']" aria-label="Listing funnel" height="h-48" />
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ $funnel['view_to_reservation'] }}% of views became a reservation; {{ $funnel['reservation_to_sale'] }}% of reservations became a sale.
                    </p>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Peak activity times</x-slot>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Busiest days</p>
                            <x-chart-js id="peak-days" type="bar" :data="$charts['peak-days']" aria-label="Busiest days" height="h-48" />
                        </div>
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Busiest hours</p>
                            @forelse (array_slice($peakTimes['hours'], 0, 5) as $row)
                                <div class="flex items-center justify-between border-b border-gray-100 py-1.5 text-sm last:border-0 dark:border-gray-800">
                                    <span>{{ sprintf('%02d:00', $row['hour']) }}</span>
                                    <span class="font-medium">{{ $row['total'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">No data yet.</p>
                            @endforelse
                        </div>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
