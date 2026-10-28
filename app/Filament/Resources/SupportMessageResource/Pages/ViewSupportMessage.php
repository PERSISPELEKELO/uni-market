<?php

namespace App\Filament\Resources\SupportMessageResource\Pages;

use App\Filament\Resources\SupportMessageResource;
use App\Models\SupportMessage;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportMessage extends ViewRecord
{
    protected static string $resource = SupportMessageResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Support message')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')->label('From')->placeholder('Deleted account'),
                        TextEntry::make('user.email')->label('Email')->placeholder('-'),
                        TextEntry::make('status')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => $state === SupportMessage::STATUS_RESOLVED ? 'success' : 'warning'),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                        TextEntry::make('resolved_at')->dateTime()->placeholder('Not yet resolved'),
                        TextEntry::make('subject')->columnSpanFull(),
                        TextEntry::make('message')->columnSpanFull(),
                    ]),
            ]);
    }
}
