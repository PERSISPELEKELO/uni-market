<?php

namespace App\Filament\Resources\RatingResource\Pages;

use App\Enums\AppealStatus;
use App\Filament\Resources\RatingResource;
use App\Models\Appeal;
use App\Models\Rating;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewRating extends ViewRecord
{
    protected static string $resource = RatingResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Review')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rater.name')->label('Rated by'),
                        TextEntry::make('rated.name')->label('About'),
                        TextEntry::make('stars')->formatStateUsing(fn (int $state): string => "{$state} / 5"),
                        TextEntry::make('status')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('comment')->placeholder('No written review')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ]),

                Section::make('Transaction')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('transaction.id')->label('Transaction #'),
                        TextEntry::make('transaction.listing.title')->label('Listing')->placeholder('Listing removed'),
                        TextEntry::make('transaction.buyer.name')->label('Buyer'),
                        TextEntry::make('transaction.seller.name')->label('Seller'),
                    ]),

                Section::make('Reports against this review')
                    ->visible(fn (Rating $record): bool => $record->reports()->exists())
                    ->schema([
                        TextEntry::make('report_list')
                            ->label('')
                            ->state(function (Rating $record): string {
                                return $record->reports()->with('user')->latest()->get()
                                    ->map(function (Appeal $appeal): string {
                                        $status = $appeal->status instanceof AppealStatus ? $appeal->status->value : $appeal->status;

                                        return sprintf(
                                            '%s reported this on %s (%s): %s',
                                            $appeal->user?->name ?? 'Unknown',
                                            $appeal->created_at->format('M j, Y g:i A'),
                                            ucfirst(strtolower(str_replace('_', ' ', (string) $status))),
                                            $appeal->reason
                                        );
                                    })
                                    ->implode("\n\n");
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
