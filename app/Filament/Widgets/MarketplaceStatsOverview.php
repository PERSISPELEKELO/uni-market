<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AppealResource;
use App\Filament\Resources\DisputeResource;
use App\Filament\Resources\ListingResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\UserResource;
use App\Models\Appeal;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Every number here comes straight from the database at render time - no
 * hard-coded placeholders. Each card links to the resource it summarises.
 */
class MarketplaceStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers = User::count();
        $suspendedUsers = User::suspended()->count();

        $totalListings = Listing::count();
        $inProgressListings = Listing::where('status', Listing::STATUS_PENDING)->count();

        $totalTransactions = Transaction::count();

        $openDisputes = Dispute::whereIn('status', ['open', 'under_review'])->count();

        $pendingAppeals = Appeal::whereIn('status', [Appeal::STATUS_PENDING, Appeal::STATUS_UNDER_REVIEW])->count();

        return [
            Stat::make('Users', number_format($totalUsers))
                ->description($suspendedUsers > 0 ? "{$suspendedUsers} suspended" : 'None suspended')
                ->descriptionIcon('heroicon-m-users')
                ->color($suspendedUsers > 0 ? 'warning' : 'success')
                ->url(UserResource::getUrl('index')),

            Stat::make('Listings', number_format($totalListings))
                ->description("{$inProgressListings} sale in progress")
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->url(ListingResource::getUrl('index')),

            Stat::make('Transactions', number_format($totalTransactions))
                ->description('View all transactions')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->url(TransactionResource::getUrl('index')),

            Stat::make('Open Disputes', number_format($openDisputes))
                ->description($openDisputes > 0 ? 'Require attention' : 'All clear')
                ->descriptionIcon('heroicon-m-scale')
                ->color($openDisputes > 0 ? 'danger' : 'success')
                ->url(DisputeResource::getUrl('index')),

            Stat::make('Pending Appeals', number_format($pendingAppeals))
                ->description($pendingAppeals > 0 ? 'Awaiting review' : 'All clear')
                ->descriptionIcon('heroicon-m-flag')
                ->color($pendingAppeals > 0 ? 'warning' : 'success')
                ->url(AppealResource::getUrl('index')),
        ];
    }
}
