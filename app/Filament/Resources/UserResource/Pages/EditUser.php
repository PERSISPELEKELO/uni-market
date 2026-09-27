<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Role changes only - accounts are never deleted from here, so there is
 * deliberately no delete action on this page.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
