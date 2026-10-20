<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StudentVerificationDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the underlying image for any decided (approved or rejected)
 * verification document 30 days after that decision - the decision itself
 * (status, reviewed_at, reviewed_by, admin_notes) is kept forever; only the
 * image file is ever removed, and never a still-pending submission's.
 */
class PruneVerificationDocuments extends Command
{
    private const RETENTION_DAYS = 30;

    protected $signature = 'verification:prune-documents';

    protected $description = 'Delete verification document images 30 days after a decision, keeping the decision record';

    public function handle(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $documents = StudentVerificationDocument::query()
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<', $cutoff)
            ->whereNull('file_deleted_at')
            ->get();

        foreach ($documents as $document) {
            Storage::disk(StudentVerificationDocument::DISK)->delete($document->file_path);
            $document->update(['file_deleted_at' => now()]);
        }

        $this->info("Deleted {$documents->count()} verification document image(s) decided before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
