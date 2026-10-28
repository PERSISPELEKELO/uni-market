<?php

namespace App\Filament\Resources\StudentVerificationDocumentResource\Pages;

use App\Filament\Resources\StudentVerificationDocumentResource;
use App\Models\StudentVerificationDocument;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Collection;

class ViewSubmission extends ViewRecord
{
    protected static string $resource = StudentVerificationDocumentResource::class;

    protected static string $view = 'filament.resources.student-verification-document-resource.pages.view-submission';

    /**
     * @return Collection<int, StudentVerificationDocument>
     */
    public function history(): Collection
    {
        return $this->record->user->verificationDocuments()
            ->where('id', '!=', $this->record->id)
            ->latest('submitted_at')
            ->get();
    }

    public function getTitle(): string
    {
        return "Reviewing {$this->record->user->name}'s submission";
    }
}
