<?php

declare(strict_types=1);

namespace App\Filament\Pages;

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
