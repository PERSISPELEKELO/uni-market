<?php

namespace App\Filament\Resources\AppealResource\Pages;

use App\Enums\AppealStatus;
use App\Filament\Resources\AppealResource;
use App\Models\Appeal;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAppeal extends ViewRecord
{
    protected static string $resource = AppealResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Appeal')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')->label('Submitted by'),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                        TextEntry::make('target_type')->label('Concerning'),
                        TextEntry::make('target_id')->label('Reference #'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (AppealStatus $state): string => ucfirst(strtolower(str_replace('_', ' ', $state->value)))),
                        TextEntry::make('resolved_at')->dateTime()->placeholder('Not yet resolved'),
                        TextEntry::make('reason')->columnSpanFull(),
                    ]),

                Section::make('Evidence')
                    ->visible(fn (Appeal $record): bool => filled($record->evidence_urls))
                    ->schema([
                        TextEntry::make('evidence_urls')
                            ->label('Links provided')
                            ->listWithLineBreaks()
                            ->bulleted(),
                    ]),

                Section::make('Governance decision')
                    ->visible(fn (Appeal $record): bool => $record->isResolved())
                    ->schema([
                        TextEntry::make('governance_notes')->label('Notes')->columnSpanFull(),
                    ]),
            ]);
    }
}
