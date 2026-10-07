@php
    $totals = $this->totals();
    $trust = $this->trust();
    $funnel = $this->listingFunnel();
    $fastest = $this->fastestSelling();
    $peakTimes = $this->peakTimes();
    $yearHeatmap = $this->buyersByYearAndCategory();
    $schoolHeatmap = $this->buyersBySchoolAndCategory();
    $userGrowth = $this->userGrowth();
    $shape = $this->sellersVsBuyersVsBoth();
    $topListings = $this->topListings();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <label for="period" class="text-sm font-medium text-gray-700 dark:text-gray-300">Period</label>
                <select id="period" wire:model.live="period" class="fi-select-input mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700">
                    @foreach ($this->periodOptions() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Breakdowns naming a group of students only appear once at least {{ config('insights.min_group_size') }} distinct students are included.
            </p>
        </div>
    </x-filament::section>

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

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-1.5 pr-4 font-medium">Week starting</th>
                        <th class="py-1.5 pr-4 font-medium">Value (K)</th>
                        <th class="py-1.5 font-medium">Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->valueOverTime() as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-1.5 pr-4">{{ $row['week'] }}</td>
                            <td class="py-1.5 pr-4 font-medium">{{ number_format($row['value'], 2) }}</td>
                            <td class="py-1.5">{{ $row['transactions'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">User growth (new accounts per week)</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="py-1.5 pr-4 font-medium">Week starting</th>
                            <th class="py-1.5 font-medium">New accounts</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($userGrowth as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-1.5 pr-4">{{ $row['week'] }}</td>
                                <td class="py-1.5 font-medium">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Buyers vs. sellers vs. both</x-slot>
            <x-slot name="description">Among everyone with at least one completed transaction in this period.</x-slot>

            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $shape['buyers_only'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Buyers only</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $shape['sellers_only'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Sellers only</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $shape['both'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Both</p>
                </div>
            </div>
        </x-filament::section>
    </div>

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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Fastest-selling categories</x-slot>

            @forelse ($fastest['categories'] as $row)
                <div class="flex items-center justify-between border-b border-gray-100 py-1.5 text-sm last:border-0 dark:border-gray-800">
                    <span>{{ $row['category'] }}</span>
                    <span class="font-medium">{{ $row['median_days'] }} days (median)</span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Not enough completed sales yet.</p>
            @endforelse
        </x-filament::section>

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
    </div>

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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Listing funnel</x-slot>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between"><span>Views</span><span class="font-semibold">{{ number_format($funnel['views']) }}</span></div>
                <div class="flex items-center justify-between"><span>Reservations</span><span class="font-semibold">{{ number_format($funnel['reservations']) }} ({{ $funnel['view_to_reservation'] }}% of views)</span></div>
                <div class="flex items-center justify-between"><span>Completed sales</span><span class="font-semibold">{{ number_format($funnel['sales']) }} ({{ $funnel['reservation_to_sale'] }}% of reservations)</span></div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Peak activity times</x-slot>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Busiest days</p>
                    @forelse (array_slice($peakTimes['days'], 0, 3) as $row)
                        <div class="flex items-center justify-between"><span>{{ $row['day'] }}</span><span class="font-medium">{{ $row['total'] }}</span></div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400">No data yet.</p>
                    @endforelse
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Busiest hours</p>
                    @forelse (array_slice($peakTimes['hours'], 0, 3) as $row)
                        <div class="flex items-center justify-between"><span>{{ sprintf('%02d:00', $row['hour']) }}</span><span class="font-medium">{{ $row['total'] }}</span></div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400">No data yet.</p>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Trust &amp; safety metrics</x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Dispute rate</p>
                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $trust['dispute_rate'] }}%</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Average resolution time</p>
                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $trust['average_resolution_days'] !== null ? $trust['average_resolution_days'].' days' : '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Verification approval rate</p>
                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $trust['verification_approval_rate'] !== null ? $trust['verification_approval_rate'].'%' : '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Average seller rating</p>
                <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $trust['average_seller_rating'] !== null ? $trust['average_seller_rating'].' / 5.0' : '—' }}</p>
            </div>
        </div>

        @if (! empty($trust['dispute_rate_by_category']))
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="py-1.5 pr-2 font-medium">Category</th>
                            <th class="py-1.5 font-medium">Dispute rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trust['dispute_rate_by_category'] as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-1.5 pr-2">{{ $row['category'] }}</td>
                                <td class="py-1.5">{{ $row['rate'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($trust['ai_agreement_rate'] !== null)
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                AI-suggested resolution agreed with the admin's final decision in {{ $trust['ai_agreement_rate'] }}% of resolved, AI-analysed disputes in this period.
            </p>
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
</x-filament-panels::page>
