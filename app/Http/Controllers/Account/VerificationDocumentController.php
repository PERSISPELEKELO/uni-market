<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\StudentVerificationDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationDocumentController extends Controller
{
    /**
     * Stream a submitted verification document to its owner or to staff only.
     * The file lives on the private disk and has no public URL, so this
     * authenticated, authorized route is the only way to ever reach it.
     */
    public function show(StudentVerificationDocument $document): StreamedResponse|Response
    {
        Gate::authorize('view', $document);

        if ($document->isFileDeleted()) {
            abort(410, 'This document has been deleted. Only the decision on it is kept.');
        }

        return Storage::disk(StudentVerificationDocument::DISK)->response(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type]
        );
    }
}
