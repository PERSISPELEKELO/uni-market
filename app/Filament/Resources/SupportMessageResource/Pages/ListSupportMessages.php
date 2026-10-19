<?php

namespace App\Filament\Resources\SupportMessageResource\Pages;

use App\Filament\Resources\SupportMessageResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No "create" header action - support messages only ever come from a
 * student's Help panel submission, through the app.
 */
class ListSupportMessages extends ListRecords
{
    protected static string $resource = SupportMessageResource::class;
}
