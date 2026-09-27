<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('role')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'admin' => 'Admin',
                                'governance_committee' => 'Governance Committee',
                                default => 'Student',
                            }),
                        TextEntry::make('student_id')->label('Student ID')->placeholder('Not provided'),
                        TextEntry::make('verificationStatusLabel')
                            ->label('Verification status')
                            ->state(fn (User $record): string => $record->verificationStatusLabel()),
                        TextEntry::make('created_at')->label('Registered')->dateTime(),
                    ]),

                Section::make('Moderation')
                    ->columns(2)
                    ->visible(fn (User $record): bool => $record->isSuspended())
                    ->schema([
                        TextEntry::make('suspended_at')->label('Suspended since')->dateTime(),
                        TextEntry::make('suspendedBy.name')->label('Suspended by')->placeholder('Unknown'),
                        TextEntry::make('suspension_reason')->label('Reason')->columnSpanFull(),
                    ]),

                Section::make('Marketplace activity')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('listings_count')
                            ->label('Listings')
                            ->state(fn (User $record): int => $record->listings()->count()),
                        TextEntry::make('purchases_count')
                            ->label('Purchases (as buyer)')
                            ->state(fn (User $record): int => $record->purchases()->count()),
                        TextEntry::make('ratings_received_count')
                            ->label('Ratings received')
                            ->state(fn (User $record): int => $record->ratingsReceived()->count()),
                        TextEntry::make('average_rating')
                            ->label('Average rating')
                            ->state(fn (User $record): string => $record->averageRating() !== null ? number_format($record->averageRating(), 1).' / 5' : 'No ratings yet'),
                    ]),
            ]);
    }
}
