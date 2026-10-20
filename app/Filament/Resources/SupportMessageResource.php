<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportMessageResource\Pages;
use App\Models\SupportMessage;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Admin triage queue for the Help panel's "Contact support" form - kept
 * separate from the peer-to-peer Message model, the same reasoning as
 * Disputes/Appeals having their own dedicated models.
 */
class SupportMessageResource extends Resource
{
    protected static ?string $model = SupportMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Support messages';

    public static function canViewAny(): bool
    {
        return Gate::allows('access-governance');
    }

    public static function canCreate(): bool
    {
        return false; // Support messages only ever come from a student's Help panel submission.
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('From')
                    ->placeholder('Deleted account')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(60),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        SupportMessage::STATUS_OPEN => 'warning',
                        SupportMessage::STATUS_RESOLVED => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->dateTime()
                    ->placeholder('Not yet resolved')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        SupportMessage::STATUS_OPEN => 'Open',
                        SupportMessage::STATUS_RESOLVED => 'Resolved',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('resolve')
                    ->label('Mark resolved')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SupportMessage $record): bool => $record->status === SupportMessage::STATUS_OPEN)
                    ->action(function (SupportMessage $record): void {
                        $record->update(['status' => SupportMessage::STATUS_RESOLVED, 'resolved_at' => now()]);
                        FilamentNotification::make()->title('Marked resolved')->success()->send();
                    }),

                Tables\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (SupportMessage $record): bool => $record->status === SupportMessage::STATUS_RESOLVED)
                    ->action(function (SupportMessage $record): void {
                        $record->update(['status' => SupportMessage::STATUS_OPEN, 'resolved_at' => null]);
                        FilamentNotification::make()->title('Reopened')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportMessages::route('/'),
            'view' => Pages\ViewSupportMessage::route('/{record}'),
        ];
    }
}
