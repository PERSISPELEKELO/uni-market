<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Security & Compliance';
    protected static ?string $navigationLabel = 'Audit Logs';

    public static function canCreate(): bool
    {
        return false; // Read-only data table for security audit trails
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('action')->disabled(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled(),
                Forms\Components\TextInput::make('entity_type')->disabled(),
                Forms\Components\TextInput::make('entity_id')->disabled(),
                Forms\Components\TextInput::make('ip_address')->disabled(),
                Forms\Components\KeyValue::make('payload')->disabled()->columnSpanFull(),
                Forms\Components\DateTimePicker::make('created_at')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->colors([
                        'primary' => fn ($state) => str_contains($state, 'INITIATED'),
                        'success' => fn ($state) => str_contains($state, 'COMPLETED') || str_contains($state, 'REGISTERED'),
                        'warning' => fn ($state) => str_contains($state, 'DISPUTE'),
                    ])
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),

                Tables\Columns\TextColumn::make('entity_type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('entity_id')
                    ->label('Entity #'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'TRANSACTION_INITIATED' => 'Transaction Initiated',
                        'TRANSACTION_COMPLETED' => 'Transaction Completed',
                        'DISPUTE_RAISED' => 'Dispute Raised',
                        'LISTING_CREATED' => 'Listing Created',
                        'USER_REGISTERED' => 'User Registered',
                        'USER_LOGIN' => 'User Login',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
