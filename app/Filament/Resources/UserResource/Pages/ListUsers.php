<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No "create" header action - accounts only ever come from students
 * registering themselves through the marketplace.
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
