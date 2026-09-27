<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only: transactions are created and driven entirely by the campus
 * handover/escrow flow (see HandoverVerificationService, InspectionService).
 * `amount` is the price the buyer and seller agreed to hand over in person -
 * this system does not process real payments, so nothing here should imply
 * a payment gateway is involved.
 */
class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('listing.title')
                    ->label('Item')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('buyer.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('seller.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Agreed amount')
                    ->money('ZMW')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match (strtoupper($state)) {
                        'INITIATED', 'RESERVED', 'PENDING' => 'Meet-up pending',
                        'ITEM_INSPECTION', 'HANDED_OVER' => 'Inspection window',
                        'COMPLETED' => 'Completed',
                        'DISPUTED' => 'Disputed',
                        'REFUNDED' => 'Refunded',
                        default => ucfirst(strtolower($state)),
                    })
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'COMPLETED' => 'success',
                        'DISPUTED' => 'danger',
                        'REFUNDED' => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('dispute')
                    ->label('Dispute')
                    ->state(fn (Transaction $record): bool => $record->dispute !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-scale')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'INITIATED' => 'Meet-up pending',
                        'ITEM_INSPECTION' => 'Inspection window',
                        'COMPLETED' => 'Completed',
                        'DISPUTED' => 'Disputed',
                        'REFUNDED' => 'Refunded',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_dispute')
                    ->label('View dispute')
                    ->icon('heroicon-o-scale')
                    ->color('danger')
                    ->visible(fn (Transaction $record): bool => $record->dispute !== null)
                    ->url(fn (Transaction $record) => DisputeResource::getUrl('edit', ['record' => $record->dispute])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}
