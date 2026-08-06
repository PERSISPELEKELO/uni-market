<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisputeResource\Pages;
use App\Models\Dispute;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Moderation & Disputes';
    protected static ?string $navigationLabel = 'Escrow Disputes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dispute Details & AI Assessment')
                    ->schema([
                        Forms\Components\Select::make('transaction_id')
                            ->relationship('transaction', 'id')
                            ->disabled()
                            ->required(),

                        Forms\Components\Select::make('raised_by')
                            ->relationship('reporter', 'name')
                            ->disabled()
                            ->required(),

                        Forms\Components\Textarea::make('reason')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'under_review' => 'Under Review',
                                'resolved_buyer' => 'Resolved (Refund Buyer)',
                                'resolved_seller' => 'Resolved (Release to Seller)',
                            ])
                            ->required(),

                        Forms\Components\Fieldset::make('Python AI Sentiment Engine Output')
                            ->schema([
                                Forms\Components\TextInput::make('ai_sentiment_score')
                                    ->numeric()
                                    ->prefix('Score (-1 to +1)')
                                    ->disabled(),

                                Forms\Components\TextInput::make('ai_confidence_score')
                                    ->numeric()
                                    ->prefix('Confidence %')
                                    ->disabled(),

                                Forms\Components\TextInput::make('ai_suggested_resolution')
                                    ->disabled(),

                                Forms\Components\Textarea::make('ai_analysis_summary')
                                    ->columnSpanFull()
                                    ->disabled(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction.id')
                    ->label('Tx #')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Raised By')
                    ->searchable(),

                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (Dispute $record): string => $record->reason),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'open',
                        'primary' => 'under_review',
                        'success' => 'resolved_buyer',
                        'success' => 'resolved_seller',
                    ]),

                Tables\Columns\TextColumn::make('ai_sentiment_score')
                    ->label('AI Sentiment')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 2) : 'N/A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ai_confidence_score')
                    ->label('Confidence')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100) . '%' : 'N/A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'under_review' => 'Under Review',
                        'resolved_buyer' => 'Resolved (Buyer)',
                        'resolved_seller' => 'Resolved (Seller)',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resolve_buyer')
                    ->label('Refund Buyer')
                    ->color('success')
                    ->icon('heroicon-o-arrow-left-on-inside-card')
                    ->action(function (Dispute $record) {
                        $record->update(['status' => 'resolved_buyer']);
                        $record->transaction->update(['status' => 'refunded']);

                        app(\App\Services\AuditLoggerService::class)->recordAction(
                            Auth::user(),
                            'DISPUTE_RESOLVED_BUYER',
                            'Dispute',
                            (string) $record->id,
                            ['resolution' => 'refunded_buyer']
                        );
                    }),
                Tables\Actions\Action::make('resolve_seller')
                    ->label('Release to Seller')
                    ->color('primary')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (Dispute $record) {
                        $record->update(['status' => 'resolved_seller']);
                        $record->transaction->update(['status' => 'completed']);
                        $record->transaction->listing->update(['status' => 'sold']);

                        app(\App\Services\AuditLoggerService::class)->recordAction(
                            Auth::user(),
                            'DISPUTE_RESOLVED_SELLER',
                            'Dispute',
                            (string) $record->id,
                            ['resolution' => 'released_to_seller']
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisputes::route('/'),
            'edit' => Pages\EditDispute::route('/{record}/edit'),
        ];
    }
}
