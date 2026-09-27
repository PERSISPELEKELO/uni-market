<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\UserModerationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Account management only - no "create" here, since accounts only ever
 * come from students registering themselves. Deletion is real but
 * deliberately narrow: an account with any marketplace history at all
 * (listings, transactions, messages, ratings, disputes, appeals) can only
 * be suspended, never deleted - see UserModerationService::delete().
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Users';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options([
                        'student' => 'Student',
                        'governance_committee' => 'Governance Committee',
                        'admin' => 'Admin',
                    ])
                    ->required()
                    ->disabled(fn (?User $record): bool => $record !== null && Auth::id() === $record->id)
                    ->helperText('Only admins can change a role, and never their own.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Admin',
                        'governance_committee' => 'Governance Committee',
                        default => 'Student',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'governance_committee' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (User $record): string => $record->isSuspended() ? 'Suspended' : 'Active')
                    ->badge()
                    ->color(fn (User $record): string => $record->isSuspended() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('listings_count')
                    ->label('Listings')
                    ->counts('listings')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'student' => 'Student',
                        'governance_committee' => 'Governance Committee',
                        'admin' => 'Admin',
                    ]),

                Tables\Filters\TernaryFilter::make('suspended')
                    ->label('Account status')
                    ->placeholder('All accounts')
                    ->trueLabel('Suspended only')
                    ->falseLabel('Active only')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('suspended_at'),
                        false: fn ($query) => $query->whereNull('suspended_at'),
                    ),

                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified student'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->label('Change role')
                    ->visible(fn (User $record): bool => Gate::allows('manage-marketplace') && Auth::id() !== $record->id),

                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => Gate::allows('suspend', $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record): string => "This immediately signs {$record->name} out and blocks them from logging back in until reinstated.")
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason (kept on record)')
                            ->required()
                            ->minLength(10),
                    ])
                    ->action(function (User $record, array $data, UserModerationService $service): void {
                        try {
                            $service->suspend($record, Auth::user(), $data['reason']);
                            FilamentNotification::make()->title('Account suspended')->success()->send();
                        } catch (InvalidArgumentException $exception) {
                            FilamentNotification::make()->title('Could not suspend this account')->body($exception->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('reinstate')
                    ->label('Reinstate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->isSuspended() && Gate::allows('reinstate', $record))
                    ->requiresConfirmation()
                    ->modalDescription('This immediately restores full access to the account.')
                    ->action(function (User $record, UserModerationService $service): void {
                        try {
                            $service->reinstate($record, Auth::user());
                            FilamentNotification::make()->title('Account reinstated')->success()->send();
                        } catch (InvalidArgumentException $exception) {
                            FilamentNotification::make()->title('Could not reinstate this account')->body($exception->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('delete_user')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (User $record): bool => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Permanently delete this account?')
                    ->modalDescription('This cannot be undone. It only succeeds if the account has no listings, transactions, messages, ratings, disputes or appeals - suspend it instead if it has any history.')
                    ->modalSubmitActionLabel('Delete permanently')
                    ->action(function (User $record, UserModerationService $service): void {
                        try {
                            $service->delete($record, Auth::user());
                            FilamentNotification::make()->title('Account deleted')->success()->send();
                        } catch (InvalidArgumentException $exception) {
                            FilamentNotification::make()->title('Could not delete this account')->body($exception->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
