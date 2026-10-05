<?php

namespace App\Filament\Resources\RatingResource\Pages;

use App\Filament\Resources\RatingResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No "create" header action - ratings only ever come from a completed
 * transaction, through the app.
 */
class ListRatings extends ListRecords
{
    protected static string $resource = RatingResource::class;
}
