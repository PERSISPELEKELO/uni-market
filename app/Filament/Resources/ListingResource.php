<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ListingResource\Pages;
use App\Models\Listing;
use App\Services\AuditLoggerService;
use Filament\Forms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Listings are created by sellers through the marketplace, never here.
 * Admin's only real levers are suspending one (hiding it from the
 * marketplace) and restoring it - there is no separate approval queue in
 * this system, so this resource doesn't pretend one exists.
 */
class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(35)
                    ->sortable(),

                Tables\Columns\TextColumn::make('seller.name')
                    ->label('Seller')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->money('ZMW')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Listing::STATUS_ACTIVE => 'Active',
                        Listing::STATUS_PENDING => 'Sale in progress',
                        Listing::STATUS_SOLD => 'Sold',
                        Listing::STATUS_SUSPENDED => 'Suspended',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Listing::STATUS_ACTIVE => 'success',
                        Listing::STATUS_PENDING => 'info',
                        Listing::STATUS_SOLD => 'gray',
                        Listing::STATUS_SUSPENDED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Listed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Listing::STATUS_ACTIVE => 'Active',
                        Listing::STATUS_PENDING => 'Sale in progress',
                        Listing::STATUS_SOLD => 'Sold',
                        Listing::STATUS_SUSPENDED => 'Suspended',
                    ]),

                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_on_marketplace')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Listing $record): string => route('listings.show', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (Listing $record): bool => Gate::allows('manage-marketplace') && $record->status !== Listing::STATUS_SUSPENDED)
                    ->requiresConfirmation()
                    ->modalDescription('This immediately hides the listing from the marketplace. It stays hidden until restored.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason (kept on record)')
                            ->required()
                            ->minLength(10),
                    ])
                    ->action(function (Listing $record, array $data): void {
                        $previousStatus = $record->status;
                        $record->update(['status' => Listing::STATUS_SUSPENDED]);

                        app(AuditLoggerService::class)->log(
                            'LISTING_SUSPENDED',
                            'Listing',
                            $record->id,
                            ['previous_status' => $previousStatus, 'reason' => $data['reason']],
                            Auth::user()
                        );

                        FilamentNotification::make()->title('Listing suspended')->success()->send();
                    }),

                Tables\Actions\Action::make('restore_listing')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (Listing $record): bool => Gate::allows('manage-marketplace') && $record->status === Listing::STATUS_SUSPENDED)
                    ->requiresConfirmation()
                    ->modalDescription('This makes the listing visible on the marketplace again.')
                    ->action(function (Listing $record): void {
                        $record->update(['status' => Listing::STATUS_ACTIVE]);

                        app(AuditLoggerService::class)->log(
                            'LISTING_RESTORED',
                            'Listing',
                            $record->id,
                            [],
                            Auth::user()
                        );

                        FilamentNotification::make()->title('Listing restored')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListings::route('/'),
        ];
    }
}
