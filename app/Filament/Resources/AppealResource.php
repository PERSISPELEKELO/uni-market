<?php

namespace App\Filament\Resources;

use App\Enums\AppealStatus;
use App\Filament\Resources\AppealResource\Pages;
use App\Models\Appeal;
use App\Services\AppealWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Reports tab: this system doesn't have a separate "report a listing/user"
 * feature, so real appeals - a student contesting a moderation decision -
 * are the closest existing thing to a report queue, and are surfaced here
 * rather than inventing new data.
 */
class AppealResource extends Resource
{
    protected static ?string $model = Appeal::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Reports & Appeals';

    protected static ?string $modelLabel = 'appeal';

    public static function canViewAny(): bool
    {
        return Gate::allows('access-governance');
    }

    public static function canCreate(): bool
    {
        return false; // Appeals only ever come from a student contesting a decision, via the app.
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Reported by')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_type')
                    ->label('Concerning'),

                Tables\Columns\TextColumn::make('reason')
                    ->limit(50)
                    ->tooltip(fn (Appeal $record): string => $record->reason),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AppealStatus|string $state): string => is_string($state) ? ucfirst(strtolower(str_replace('_', ' ', $state))) : ucfirst(strtolower(str_replace('_', ' ', $state->value))))
                    ->color(fn (AppealStatus|string $state): string => match ($state instanceof AppealStatus ? $state->value : $state) {
                        AppealStatus::PENDING->value => 'warning',
                        AppealStatus::UNDER_REVIEW->value => 'info',
                        AppealStatus::UPHELD->value => 'success',
                        AppealStatus::OVERTURNED->value => 'danger',
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
                        AppealStatus::PENDING->value => 'Pending',
                        AppealStatus::UNDER_REVIEW->value => 'Under review',
                        AppealStatus::UPHELD->value => 'Upheld',
                        AppealStatus::OVERTURNED->value => 'Overturned',
                    ]),

                Tables\Filters\SelectFilter::make('target_type')
                    ->label('Type'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('start_review')
                    ->label('Start review')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(fn (Appeal $record): bool => $record->isPending())
                    ->action(function (Appeal $record, AppealWorkflowService $service): void {
                        try {
                            $service->startReview($record, Auth::user());
                            FilamentNotification::make()->title('Review started')->success()->send();
                        } catch (Throwable $exception) {
                            FilamentNotification::make()->title('Could not start review')->body($exception->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('uphold')
                    ->label('Uphold decision')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (Appeal $record): bool => $record->isUnderReview())
                    ->requiresConfirmation()
                    ->modalDescription('Upholding means the original moderation decision stands.')
                    ->form([
                        Forms\Components\Textarea::make('notes')->label('Notes (kept on record)')->required()->minLength(10),
                    ])
                    ->action(function (Appeal $record, array $data, AppealWorkflowService $service): void {
                        try {
                            $service->decideAppeal($record, AppealStatus::UPHELD->value, $data['notes'], Auth::user());
                            FilamentNotification::make()->title('Decision recorded: upheld')->success()->send();
                        } catch (Throwable $exception) {
                            FilamentNotification::make()->title('Could not record this decision')->body($exception->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('overturn')
                    ->label('Overturn decision')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Appeal $record): bool => $record->isUnderReview())
                    ->requiresConfirmation()
                    ->modalDescription('Overturning means the original moderation decision is reversed.')
                    ->form([
                        Forms\Components\Textarea::make('notes')->label('Notes (kept on record)')->required()->minLength(10),
                    ])
                    ->action(function (Appeal $record, array $data, AppealWorkflowService $service): void {
                        try {
                            $service->decideAppeal($record, AppealStatus::OVERTURNED->value, $data['notes'], Auth::user());
                            FilamentNotification::make()->title('Decision recorded: overturned')->success()->send();
                        } catch (Throwable $exception) {
                            FilamentNotification::make()->title('Could not record this decision')->body($exception->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppeals::route('/'),
            'view' => Pages\ViewAppeal::route('/{record}'),
        ];
    }
}
