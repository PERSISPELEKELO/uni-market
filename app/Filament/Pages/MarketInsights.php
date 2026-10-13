<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Insights\ChartPalette;
use App\Services\Insights\FrequentlyBoughtTogetherService;
use App\Services\Insights\MarketInsightsService;
use App\Services\Insights\SellerInsightsService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Campus-wide BI dashboard for admins and the Governance Committee. Every
 * number here comes straight from MarketInsightsService, which already
 * applies the config('insights.min_group_size') suppression to anything
 * that would otherwise name a small group of students - this page never
 * re-implements or bypasses that suppression.
 */
class MarketInsights extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Business Intelligence';

    protected static ?string $navigationLabel = 'Market Insights';

    protected static string $view = 'filament.pages.market-insights';

    public string $period = '30d';

    public string $section = 'overview';

    public ?string $selectedCategory = null;

    private const SECTIONS = ['overview', 'marketplace', 'growth', 'trust', 'listings'];

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return [
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            'semester' => 'This semester (120 days)',
            '12mo' => 'Last 12 months',
        ];
    }

    public function setSection(string $section): void
    {
        if (in_array($section, self::SECTIONS, true)) {
            $this->section = $section;
            $this->selectedCategory = null;
        }
    }

    public function applyChartFilter(string $dimension, string $value): void
    {
        if ($dimension === 'category') {
            $this->selectedCategory = $value;
            $this->dispatch('charts-updated', charts: $this->chartsForCurrentSection());
        }
    }

    public function resetChartFilters(): void
    {
        $this->selectedCategory = null;
        $this->dispatch('charts-updated', charts: $this->chartsForCurrentSection());
    }

    public function updatedPeriod(): void
    {
        $this->dispatch('charts-updated', charts: $this->chartsForCurrentSection());
    }

    /**
     * @return array<string, array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}>
     */
    public function chartsForCurrentSection(): array
    {
        $service = app(MarketInsightsService::class);

        return match ($this->section) {
            'growth' => [
                'user-growth' => $this->lineChart($service->userGrowth($this->period), 'week', 'total', 'New accounts'),
                'buyer-seller-shape' => $this->shapeChart($service->sellersVsBuyersVsBoth($this->period)),
            ],
            'trust' => [
                'dispute-rate-by-category' => $this->categoryRateChart($service->trust($this->period)['dispute_rate_by_category']),
                'fastest-selling' => $this->fastestSellingChart($service->fastestSelling($this->period)['categories']),
            ],
            'listings' => [
                'funnel' => $this->funnelChart($service->listingFunnel($this->period)),
                'peak-days' => $this->peakChart($service->peakTimes($this->period)['days'], 'day'),
            ],
            default => [
                'value-over-time' => $this->lineChart($service->valueOverTime($this->period), 'week', 'value', 'Value (K)'),
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function lineChart(array $rows, string $labelKey, string $valueKey, string $label): array
    {
        return [
            'labels' => array_column($rows, $labelKey),
            'datasets' => [[
                'label' => $label,
                'data' => array_column($rows, $valueKey),
                'borderColor' => ChartPalette::COLORS[0],
                'backgroundColor' => ChartPalette::COLORS[0].'33',
                'fill' => true,
                'tension' => 0.3,
            ]],
        ];
    }

    /**
     * @param  array{buyers_only: int, sellers_only: int, both: int}  $shape
     */
    private function shapeChart(array $shape): array
    {
        return [
            'labels' => ['Buyers only', 'Sellers only', 'Both'],
            'datasets' => [[
                'data' => [$shape['buyers_only'], $shape['sellers_only'], $shape['both']],
                'backgroundColor' => ChartPalette::take(3),
            ]],
            'centerText' => (string) ($shape['buyers_only'] + $shape['sellers_only'] + $shape['both']),
        ];
    }

    /**
     * @param  array<int, array{category: string, rate: float}>  $rows
     */
    private function categoryRateChart(array $rows): array
    {
        $rows = $this->selectedCategory !== null
            ? array_values(array_filter($rows, fn (array $row) => $row['category'] === $this->selectedCategory))
            : $rows;

        $labels = array_column($rows, 'category');

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Dispute rate (%)',
                'data' => array_column($rows, 'rate'),
                'backgroundColor' => ChartPalette::take(count($labels)),
            ]],
        ];
    }

    /**
     * @param  array<int, array{category: string, median_days: float}>  $rows
     */
    private function fastestSellingChart(array $rows): array
    {
        $rows = $this->selectedCategory !== null
            ? array_values(array_filter($rows, fn (array $row) => $row['category'] === $this->selectedCategory))
            : $rows;

        return [
            'labels' => array_column($rows, 'category'),
            'datasets' => [[
                'label' => 'Median days to sell',
                'data' => array_column($rows, 'median_days'),
                'backgroundColor' => ChartPalette::COLORS[1],
            ]],
        ];
    }

    /**
     * @param  array{views: int, reservations: int, sales: int, view_to_reservation: float, reservation_to_sale: float}  $funnel
     */
    private function funnelChart(array $funnel): array
    {
        return [
            'labels' => ['Views', 'Reservations', 'Sales'],
            'datasets' => [[
                'label' => 'Count',
                'data' => [$funnel['views'], $funnel['reservations'], $funnel['sales']],
                'backgroundColor' => ChartPalette::take(3),
            ]],
        ];
    }

    /**
     * @param  array<int, array{day?: string, hour?: int, total: int}>  $rows
     */
    private function peakChart(array $rows, string $labelKey): array
    {
        return [
            'labels' => array_column($rows, $labelKey),
            'datasets' => [[
                'label' => 'Completed sales',
                'data' => array_column($rows, 'total'),
                'backgroundColor' => ChartPalette::take(count($rows)),
            ]],
        ];
    }

    public function totals(): array
    {
        return app(MarketInsightsService::class)->totals($this->period);
    }

    public function valueOverTime(): array
    {
        return app(MarketInsightsService::class)->valueOverTime($this->period);
    }

    public function userGrowth(): array
    {
        return app(MarketInsightsService::class)->userGrowth($this->period);
    }

    public function sellersVsBuyersVsBoth(): array
    {
        return app(MarketInsightsService::class)->sellersVsBuyersVsBoth($this->period);
    }

    public function topListings(): array
    {
        return app(MarketInsightsService::class)->topListings($this->period);
    }

    public function buyersByYearAndCategory(): array
    {
        return app(MarketInsightsService::class)->buyersByYearAndCategory($this->period);
    }

    public function buyersBySchoolAndCategory(): array
    {
        return app(MarketInsightsService::class)->buyersBySchoolAndCategory($this->period);
    }

    public function buyersByGenderAndCategory(): array
    {
        return app(MarketInsightsService::class)->buyersByGenderAndCategory($this->period);
    }

    public function fastestSelling(): array
    {
        return app(MarketInsightsService::class)->fastestSelling($this->period);
    }

    public function supplyVsDemand(): array
    {
        return app(SellerInsightsService::class)->demandHints();
    }

    public function unmetSearches(): array
    {
        return app(MarketInsightsService::class)->unmetSearches();
    }

    public function listingFunnel(): array
    {
        return app(MarketInsightsService::class)->listingFunnel($this->period);
    }

    public function peakTimes(): array
    {
        return app(MarketInsightsService::class)->peakTimes($this->period);
    }

    public function trust(): array
    {
        return app(MarketInsightsService::class)->trust($this->period);
    }

    public function frequentlyBoughtTogether(): array
    {
        return app(FrequentlyBoughtTogetherService::class)->topPairs(10)->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /**
     * Only ever exports figures that are already safe to show on this page
     * (market-wide totals, category-level breakdowns, and the same
     * group-suppressed heatmap cells rendered above) - never a raw,
     * per-student row.
     */
    private function exportCsv(): StreamedResponse
    {
        $totals = $this->totals();
        $trust = $this->trust();

        return response()->streamDownload(function () use ($totals, $trust) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Market Insights export', 'Period: '.$this->period, 'Generated: '.now()->toDateTimeString()]);
            fputcsv($handle, []);

            fputcsv($handle, ['Metric', 'Value', 'Change vs previous period']);
            fputcsv($handle, ['Active listings', $totals['active_listings'], '']);
            fputcsv($handle, ['Completed transactions', $totals['completed_transactions'], $totals['change']['transactions'].'%']);
            fputcsv($handle, ['Total value (K)', $totals['total_value'], $totals['change']['value'].'%']);
            fputcsv($handle, ['Active buyers', $totals['active_buyers'], $totals['change']['buyers'].'%']);
            fputcsv($handle, ['Active sellers', $totals['active_sellers'], $totals['change']['sellers'].'%']);
            fputcsv($handle, []);

            fputcsv($handle, ['Weekly value']);
            fputcsv($handle, ['Week starting', 'Value (K)', 'Transactions']);
            foreach ($this->valueOverTime() as $row) {
                fputcsv($handle, [$row['week'], $row['value'], $row['transactions']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Dispute rate by category']);
            fputcsv($handle, ['Category', 'Dispute rate (%)']);
            foreach ($trust['dispute_rate_by_category'] as $row) {
                fputcsv($handle, [$row['category'], $row['rate']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['User growth']);
            fputcsv($handle, ['Week starting', 'New accounts']);
            foreach ($this->userGrowth() as $row) {
                fputcsv($handle, [$row['week'], $row['total']]);
            }
            fputcsv($handle, []);

            $shape = $this->sellersVsBuyersVsBoth();
            fputcsv($handle, ['Buyers vs sellers vs both']);
            fputcsv($handle, ['Buyers only', 'Sellers only', 'Both']);
            fputcsv($handle, [$shape['buyers_only'], $shape['sellers_only'], $shape['both']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Top listings by sale value']);
            fputcsv($handle, ['Listing', 'Amount (K)']);
            foreach ($this->topListings() as $row) {
                fputcsv($handle, [$row['listing']->title, $row['amount']]);
            }

            fclose($handle);
        }, 'market-insights-'.$this->period.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
