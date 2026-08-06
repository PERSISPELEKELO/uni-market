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
                Forms\Components\TextInput::make('uuid')->disabled(),
                Forms\Components\TextInput::make('action')->disabled(),
                Forms\Components\Select::make('actor_id')
                    ->relationship('actor', 'name')
                    ->disabled(),
                Forms\Components\TextInput::make('actor_role')->disabled(),
                Forms\Components\TextInput::make('target_type')->disabled(),
                Forms\Components\TextInput::make('target_id')->disabled(),
                Forms\Components\TextInput::make('previous_hash')->disabled(),
                Forms\Components\TextInput::make('current_hash')->disabled(),
                Forms\Components\KeyValue::make('payload')->disabled()->columnSpanFull(),
                Forms\Components\DateTimePicker::make('timestamp')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uuid')->limit(8)->searchable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('actor.name')
                    ->label('Actor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('actor_role')
                    ->label('Role')
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_type')
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_id')
                    ->label('Target ID'),

                Tables\Columns\TextColumn::make('current_hash')
                    ->label('Hash (SHA256)')
                    ->limit(10),

                Tables\Columns\TextColumn::make('timestamp')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('timestamp', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
