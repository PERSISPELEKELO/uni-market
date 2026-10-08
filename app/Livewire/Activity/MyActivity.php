<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\Transaction;
use App\Services\Insights\BuyerInsightsService;
use App\Services\Insights\ChartPalette;
use App\Services\Insights\PeriodBoundary;
use App\Services\Insights\SellerInsightsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The single hub for "things I'm doing on the marketplace" - merges what
 * used to be two separate nav destinations (My listings, My transactions)
 * into one page with tabs, plus a new Insights tab. Deliberately does not
 * rebuild Tracker or MyListings: each tab embeds the existing, already
 * tested component unchanged, so nothing about how they work changes here.
 *
 * The Insights tab itself has its own inner sidebar (Overview/Selling/
 * Buying/Customers) - every chart on it is rendered with Chart.js. The
 * server (this class + the Insights services) computes every number;
 * JavaScript only ever draws what it's given, including after a filter
 * change, via the `charts-updated` browser event (see resources/js/app.js).
 * Cross-filtering is implemented everywhere it's genuinely meaningful:
 * clicking a category in the sold/spending-by-category charts narrows the
 * ranked price-check table and re-highlights the clicked slice. It does
 * not force a "category" filter onto charts with no category dimension at
 * all (e.g. earnings-over-time) - that would just be noise, not a filter.
 */
class MyActivity extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'buying';

    #[Url(as: 'period')]
    public string $insightsPeriod = 'all';

    #[Url(as: 'section')]
    public string $insightsSidebarView = 'overview';

    #[Url(as: 'category')]
    public ?string $selectedCategory = null;

    /**
     * Optional deep-link param, mirroring the existing /transactions-tracker/
     * {transaction} route, so a future link into this hub can still open one
     * specific transaction directly on the Buying tab.
     */
    public ?int $transaction = null;

    private const SIDEBAR_VIEWS = ['overview', 'selling', 'buying', 'customers'];

    public function mount(?int $transaction = null): void
    {
        $this->tab = in_array($this->tab, ['buying', 'selling', 'insights'], true) ? $this->tab : 'buying';
        $this->insightsPeriod = array_key_exists($this->insightsPeriod, PeriodBoundary::OPTIONS) ? $this->insightsPeriod : 'all';
        $this->insightsSidebarView = in_array($this->insightsSidebarView, self::SIDEBAR_VIEWS, true) ? $this->insightsSidebarView : 'overview';

        if ($transaction !== null) {
            $this->tab = 'buying';
            $this->transaction = $transaction;
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['buying', 'selling', 'insights'], true)) {
            $this->tab = $tab;
        }
    }

    public function setInsightsPeriod(string $period): void
    {
        if (array_key_exists($period, PeriodBoundary::OPTIONS)) {
            $this->insightsPeriod = $period;
            $this->dispatch('charts-updated', charts: $this->chartsForCurrentView());
        }
    }

    public function setInsightsSidebarView(string $view): void
    {
        if (in_array($view, self::SIDEBAR_VIEWS, true)) {
            $this->insightsSidebarView = $view;
            $this->selectedCategory = null;
        }
    }

    /**
     * Fired by a chart's onclick handler (see resources/js/app.js's
     * chartTile()) via $wire.applyChartFilter(dimension, value). Only
     * 'category' is meaningful today - anything else is ignored rather
     * than silently accepted and never applied.
     */
    public function applyChartFilter(string $dimension, string $value): void
    {
        if ($dimension === 'category') {
            $this->selectedCategory = $value;
            $this->dispatch('charts-updated', charts: $this->chartsForCurrentView());
        }
    }

    public function resetChartFilters(): void
    {
        $this->selectedCategory = null;
        $this->dispatch('charts-updated', charts: $this->chartsForCurrentView());
    }

    public function render()
    {
        return view('livewire.activity.my-activity', [
            'reservationsReceived' => $this->tab === 'selling' ? $this->reservationsReceived() : collect(),
            'resolvedTransaction' => $this->resolvedTransaction(),
            'periodOptions' => PeriodBoundary::OPTIONS,
            'charts' => $this->tab === 'insights' ? $this->chartsForCurrentView() : [],
        ])->layout('layouts.app', ['title' => 'My Activity - UniMarket']);
    }

    /**
     * @return array<string, array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}>
     */
    private function chartsForCurrentView(): array
    {
        $user = Auth::user();
        $buyerInsights = app(BuyerInsightsService::class);
        $sellerInsights = app(SellerInsightsService::class);

        return match ($this->insightsSidebarView) {
            'selling' => [
                'sold-by-category' => $this->categoryChart($sellerInsights->soldByCategory($user, $this->insightsPeriod), 'total_items', 'Items sold'),
                'earnings-over-time' => $this->monthlyChart($sellerInsights->earningsByMonth($user), 'Earnings (K)'),
                'funnel' => $this->funnelChart($sellerInsights->funnel($user, $this->insightsPeriod)),
            ],
            'buying' => [
                'spending-by-category' => $this->doughnutChart($buyerInsights->spendingByCategory($user, $this->insightsPeriod), 'amount', 'K'),
                'spending-over-time' => $this->monthlyChart($buyerInsights->spendingByMonth($user), 'Spending (K)'),
                'popular-with-you' => $this->popularWithYouChart($buyerInsights->popularWithStudentsLikeYou($user)),
            ],
            'customers' => $this->customersCharts($sellerInsights->buyersByYearAndSchool($user)),
            default => [
                'overview-trend' => $this->overviewTrendChart($buyerInsights->spendingByMonth($user), $sellerInsights->earningsByMonth($user)),
            ],
        };
    }

    /**
     * @param  array<int, array{category: string, total_items?: int, amount?: float, is_trending?: bool}>  $rows
     */
    private function categoryChart(array $rows, string $valueKey, string $label): array
    {
        $rows = $this->selectedCategory !== null
            ? array_values(array_filter($rows, fn (array $row) => $row['category'] === $this->selectedCategory))
            : $rows;

        $labels = array_column($rows, 'category');
        $colors = ChartPalette::take(count($labels));

        // The trending (highest-selling) category is highlighted in a
        // distinct colour rather than relying on an emoji in the label text,
        // since the label itself must stay an exact match for click-to-filter.
        foreach ($rows as $i => $row) {
            if (! empty($row['is_trending'])) {
                $colors[$i] = ChartPalette::COLORS[3];
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => $label,
                'data' => array_column($rows, $valueKey),
                'backgroundColor' => $colors,
            ]],
        ];
    }

    /**
     * @param  array<int, array{category: string, amount: float, percentage: float}>  $rows
     */
    private function doughnutChart(array $rows, string $valueKey, string $prefix): array
    {
        $rows = $this->selectedCategory !== null
            ? array_values(array_filter($rows, fn (array $row) => $row['category'] === $this->selectedCategory))
            : $rows;

        $labels = array_column($rows, 'category');
        $total = array_sum(array_column($rows, $valueKey));

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => array_column($rows, $valueKey),
                'backgroundColor' => ChartPalette::take(count($labels)),
            ]],
            'centerText' => $prefix.number_format($total, 2),
        ];
    }

    /**
     * @param  array<int, array{month: string, total: float}>  $rows
     */
    private function monthlyChart(array $rows, string $label): array
    {
        return [
            'labels' => array_column($rows, 'month'),
            'datasets' => [[
                'label' => $label,
                'data' => array_column($rows, 'total'),
                'borderColor' => ChartPalette::COLORS[0],
                'backgroundColor' => ChartPalette::COLORS[0].'33',
                'fill' => true,
                'tension' => 0.3,
            ]],
        ];
    }

    /**
     * @param  array<int, array{month: string, total: float}>  $spending
     * @param  array<int, array{month: string, total: float}>  $earnings
     */
    private function overviewTrendChart(array $spending, array $earnings): array
    {
        return [
            'labels' => array_column($spending, 'month'),
            'datasets' => [
                [
                    'label' => 'Spending (K)',
                    'data' => array_column($spending, 'total'),
                    'borderColor' => ChartPalette::COLORS[0],
                    'backgroundColor' => ChartPalette::COLORS[0].'33',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Earnings (K)',
                    'data' => array_column($earnings, 'total'),
                    'borderColor' => ChartPalette::COLORS[1],
                    'backgroundColor' => ChartPalette::COLORS[1].'33',
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    private function funnelChart(array $funnel): array
    {
        return [
            'labels' => ['Views', 'Enquiries', 'Sales'],
            'datasets' => [[
                'label' => 'Count',
                'data' => [$funnel['views'], $funnel['enquiries'], $funnel['sales']],
                'backgroundColor' => ChartPalette::take(3),
            ]],
        ];
    }

    /**
     * @param  array{level: string, categories: array<int, array{name: string, total: int}>}  $popular
     */
    private function popularWithYouChart(array $popular): array
    {
        return [
            'labels' => array_column($popular['categories'], 'name'),
            'datasets' => [[
                'label' => 'Purchases',
                'data' => array_column($popular['categories'], 'total'),
                'backgroundColor' => ChartPalette::take(count($popular['categories'])),
            ]],
        ];
    }

    /**
     * @param  array{by_year: array, by_school: array, by_gender: array, suppressed: bool}  $buyers
     * @return array<string, array>
     */
    private function customersCharts(array $buyers): array
    {
        if ($buyers['suppressed']) {
            return [];
        }

        return [
            'buyers-by-school' => [
                'labels' => array_column($buyers['by_school'], 'school'),
                'datasets' => [['label' => 'Percent', 'data' => array_column($buyers['by_school'], 'percentage'), 'backgroundColor' => ChartPalette::take(count($buyers['by_school']))]],
            ],
            'buyers-by-year' => [
                'labels' => array_map(fn ($row) => 'Year '.$row['year'], $buyers['by_year']),
                'datasets' => [['label' => 'Percent', 'data' => array_column($buyers['by_year'], 'percentage'), 'backgroundColor' => ChartPalette::take(count($buyers['by_year']))]],
            ],
            'buyers-by-gender' => [
                'labels' => array_column($buyers['by_gender'], 'gender'),
                'datasets' => [['data' => array_column($buyers['by_gender'], 'percentage'), 'backgroundColor' => ChartPalette::take(count($buyers['by_gender']))]],
                'centerText' => array_sum(array_column($buyers['by_gender'], 'percentage')).'%',
            ],
        ];
    }

    /**
     * Tracker::mount() expects a hydrated Transaction (as Laravel's route
     * model binding would supply it on the standalone tracker route), not a
     * raw id, since this is a nested @livewire embed rather than a route.
     */
    private function resolvedTransaction(): ?Transaction
    {
        return $this->transaction ? Transaction::find($this->transaction) : null;
    }

    private function reservationsReceived()
    {
        return Auth::user()->listings()
            ->with(['activeReservations.buyer'])
            ->whereHas('reservations', fn ($q) => $q->active())
            ->get();
    }
}
