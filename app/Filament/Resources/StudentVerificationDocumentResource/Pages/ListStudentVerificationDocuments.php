<?php

namespace App\Filament\Resources\StudentVerificationDocumentResource\Pages;

use App\Filament\Resources\StudentVerificationDocumentResource;
use App\Livewire\Account\StudentVerification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListStudentVerificationDocuments extends ListRecords
{
    protected static string $resource = StudentVerificationDocumentResource::class;

    public function mount(): void
    {
        parent::mount();

        // Tells StudentVerification::notifyAdminsIfQueueUnattended() that
        // someone is already looking, so a fresh submission right now
        // doesn't also trigger an email alert.
        Cache::put(StudentVerification::QUEUE_LAST_VIEWED_CACHE_KEY, now(), now()->addHour());
    }
}
