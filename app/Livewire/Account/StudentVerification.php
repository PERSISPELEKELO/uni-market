<?php

namespace App\Livewire\Account;

use App\Models\StudentVerificationDocument;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class StudentVerification extends Component
{
    use WithFileUploads;

    private const MAX_SUBMISSIONS_PER_HOUR = 5;

    public $document = null;

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
            ['document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']],
            [
                'document.required' => 'Please choose your student ID document to upload.',
                'document.mimes' => 'Please upload a JPG, PNG or PDF file.',
                'document.max' => 'The file must be 5 MB or smaller.',
            ]
        );

        RateLimiter::hit($throttleKey, 3600);

        $path = $this->document->storeAs(
            StudentVerificationDocument::PATH_PREFIX.'/'.$user->id,
            Str::uuid().'.'.$this->document->getClientOriginalExtension(),
            StudentVerificationDocument::DISK
        );

        DB::transaction(function () use ($user, $path): void {
            StudentVerificationDocument::create([
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
        });

        app(AuditLoggerService::class)->log(
            'STUDENT_VERIFICATION_SUBMITTED',
            'User',
            $user->id,
            ['student_id' => $user->student_id],
            $user
        );

        $this->document = null;
        $this->dispatch('notify', type: 'success', message: 'Document submitted. An administrator will review it shortly.');
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        return view('livewire.account.student-verification', [
            'user' => $user,
            'history' => $user->verificationDocuments,
        ])->layout('layouts.app', ['title' => 'Student Verification - UniMarket']);
    }
}
