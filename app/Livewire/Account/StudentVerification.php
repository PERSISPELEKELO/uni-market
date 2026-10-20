<?php

namespace App\Livewire\Account;

use App\Models\StudentVerificationDocument;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Livewire\Component;
use Livewire\WithFileUploads;

class StudentVerification extends Component
{
    use WithFileUploads;

    private const MAX_SUBMISSIONS_PER_HOUR = 5;

    /**
     * The shorter side of the photo must be at least this many pixels -
     * small enough to let a real phone photo through, large enough that the
     * printed name and student number are actually readable.
     */
    private const MIN_DIMENSION_PX = 800;

    public $document = null;

    public bool $confirmedOwnCard = false;

    public bool $embedded = false;

    public function submit(): void
    {
        $user = Auth::user();

        if (! $user->canSubmitStudentVerification()) {
            $this->dispatch('notify', type: 'error', message: 'You cannot submit a document right now.');

            return;
        }

        $throttleKey = 'student-verification-submit|'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_SUBMISSIONS_PER_HOUR)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('document', 'Too many submissions. Please try again in '.ceil($seconds / 60).' minute(s).');

            return;
        }

        $this->validate(
            [
                'document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', $this->minimumDimensionsRule()],
                'confirmedOwnCard' => ['accepted'],
            ],
            [
                'document.required' => 'Please choose your student ID photo to upload.',
                'document.mimes' => 'Please upload a JPG, PNG or WebP photo.',
                'document.max' => 'The file must be 5 MB or smaller.',
                'confirmedOwnCard.accepted' => 'Please confirm this is your own student ID card.',
            ]
        );

        RateLimiter::hit($throttleKey, 3600);

        $path = $this->storeWithoutMetadata($user);

        $document = DB::transaction(function () use ($user, $path): StudentVerificationDocument {
            $document = StudentVerificationDocument::create([
                'user_id' => $user->id,
                'file_path' => $path,
                'original_filename' => $this->document->getClientOriginalName(),
                'mime_type' => $this->document->getMimeType(),
                'size_bytes' => $this->document->getSize(),
                'status' => StudentVerificationDocument::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            $user->forceFill([
                'student_verification_status' => User::STUDENT_VERIFICATION_PENDING,
                'student_verification_submitted_at' => now(),
                'student_verification_reviewed_at' => null,
                'student_verification_reviewed_by' => null,
                'student_verification_rejection_reason' => null,
            ])->save();

            return $document;
        });

        app(AuditLoggerService::class)->log(
            'STUDENT_VERIFICATION_SUBMITTED',
            'User',
            $user->id,
            // Never the full student number in the audit log - just that a
            // submission happened, matching how approve/reject are logged.
            ['document_id' => $document->id],
            $user
        );

        $this->document = null;
        $this->confirmedOwnCard = false;
        $this->dispatch('notify', type: 'success', message: "Document submitted. We'll review it within 24 hours.");
    }

    /**
     * Rejects a photo too small to actually read the printed name and
     * student number on - "the shorter side" so a portrait OR landscape
     * photo of the same real-world size passes equally.
     */
    private function minimumDimensionsRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                // Nothing to check - the "required"/"file" rules already
                // report the real problem for a missing or malformed upload.
                return;
            }

            try {
                $image = ImageManager::gd()->read($value->getRealPath());
            } catch (\Throwable) {
                $fail('We could not read this photo. Please try a different file.');

                return;
            }

            if (min($image->width(), $image->height()) < self::MIN_DIMENSION_PX) {
                $fail('Your photo is too small - the shorter side must be at least '.self::MIN_DIMENSION_PX.' pixels.');
            }
        };
    }

    /**
     * Decoding and re-saving through GD strips EXIF metadata (including GPS
     * location) as a natural side effect - GD's in-memory image resource
     * never carries it through - so no separate "strip EXIF" step is
     * needed. The file is written straight to the private disk; it is
     * never made public.
     */
    private function storeWithoutMetadata(User $user): string
    {
        $disk = Storage::disk(StudentVerificationDocument::DISK);
        $directory = StudentVerificationDocument::PATH_PREFIX.'/'.$user->id;
        $filename = Str::uuid().'.'.$this->document->getClientOriginalExtension();
        $relativePath = $directory.'/'.$filename;

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        ImageManager::gd()->read($this->document->getRealPath())->save($disk->path($relativePath));

        return $relativePath;
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        return view('livewire.account.student-verification', [
            'user' => $user,
            'history' => $user->verificationDocuments,
            'embedded' => $this->embedded,
        ])->layout('layouts.app', ['title' => 'Student Verification - UniMarket']);
    }
}
