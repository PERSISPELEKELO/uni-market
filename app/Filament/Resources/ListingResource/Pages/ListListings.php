<?php

namespace App\Filament\Resources\ListingResource\Pages;

use App\Filament\Resources\ListingResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No "create" header action - listings only ever come from sellers
 * publishing them through the marketplace.
 */
class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;
}
