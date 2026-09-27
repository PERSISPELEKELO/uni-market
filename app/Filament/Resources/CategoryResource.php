<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $context, $state, Forms\Set $set): void {
                        if ($context === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Used in URLs. Only lowercase letters, numbers and hyphens.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\TextColumn::make('listings_count')
                    ->label('Listings')
                    ->counts('listings')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (Category $record, Tables\Actions\DeleteAction $action): void {
                        if ($record->listings()->exists()) {
                            FilamentNotification::make()
                                ->title('Cannot delete this category')
                                ->body('It still has listings. Move or remove them first.')
                                ->danger()
                                ->send();

                            $action->cancel();

                            return;
                        }

                        $record->delete();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
