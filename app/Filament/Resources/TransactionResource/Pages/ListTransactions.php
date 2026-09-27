<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Read-only - transactions are created by the campus handover flow, never
 * by an admin.
 */
class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;
}
