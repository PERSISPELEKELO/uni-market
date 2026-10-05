<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RatingResource\Pages;
use App\Models\Appeal;
use App\Models\Rating;
use App\Services\RatingModerationService;
use Filament\Forms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Read-only browse of every real rating, plus moderation. The average/count
 * shown on profiles is always calculated from these rows (see
 * User::averageRating) - this resource can hide a review from that
 * calculation, but it never edits the stars value itself.
 */
class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Ratings & Reviews';

    protected static ?string $navigationLabel = 'Ratings & Reviews';

    public static function canViewAny(): bool
    {
        return Gate::allows('access-governance');
    }

    public static function canCreate(): bool
    {
        return false; // Ratings only ever come from a completed transaction, via the app.
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('rater.name')
                    ->label('Rated by')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rated.name')
                    ->label('About')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stars')
                    ->label('Stars')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', Rating::MAX_STARS - $state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('comment')
                    ->limit(40)
                    ->placeholder('No written review')
                    ->tooltip(fn (Rating $record): ?string => $record->comment),

                Tables\Columns\TextColumn::make('transaction.listing.title')
                    ->label('Listing')
                    ->placeholder('Listing removed'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === Rating::STATUS_HIDDEN ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('reports')
                    ->label('Reports')
                    ->state(fn (Rating $record): int => $record->reports()->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Rating::STATUS_VISIBLE => 'Visible',
                        Rating::STATUS_HIDDEN => 'Hidden',
                    ]),

                Tables\Filters\Filter::make('reported')
                    ->label('Has been reported')
                    ->query(fn (Builder $query): Builder => $query->whereIn(
                        'id',
                        Appeal::query()->where('target_type', 'Rating')->pluck('target_id')
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (Rating $record): bool => ! $record->isHidden())
                    ->requiresConfirmation()
                    ->modalDescription('Hiding removes this review from the user\'s public profile and from their average rating. It is not deleted.')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Moderation reason (kept on record)')->required()->minLength(10),
                    ])
                    ->action(function (Rating $record, array $data, RatingModerationService $service): void {
                        try {
                            $service->hide($record, Auth::user(), $data['reason']);
                            FilamentNotification::make()->title('Review hidden')->success()->send();
                        } catch (Throwable $exception) {
                            FilamentNotification::make()->title('Could not hide this review')->body($exception->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Rating $record): bool => $record->isHidden())
                    ->requiresConfirmation()
                    ->action(function (Rating $record, RatingModerationService $service): void {
                        try {
                            $service->restore($record, Auth::user());
                            FilamentNotification::make()->title('Review restored')->success()->send();
                        } catch (Throwable $exception) {
                            FilamentNotification::make()->title('Could not restore this review')->body($exception->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRatings::route('/'),
            'view' => Pages\ViewRating::route('/{record}'),
        ];
    }
}
