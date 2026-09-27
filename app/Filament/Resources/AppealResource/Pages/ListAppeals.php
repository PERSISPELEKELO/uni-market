<?php

namespace App\Filament\Resources\AppealResource\Pages;

use App\Filament\Resources\AppealResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No "create" header action - appeals only ever come from a student
 * contesting a decision, through the app.
 */
class ListAppeals extends ListRecords
{
    protected static string $resource = AppealResource::class;
}
