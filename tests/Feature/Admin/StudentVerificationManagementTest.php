<?php

use App\Filament\Resources\StudentVerificationDocumentResource\Pages\ListStudentVerificationDocuments;
use App\Models\StudentVerificationDocument;
use App\Models\User;
use App\Notifications\StudentVerificationApproved;
use App\Notifications\StudentVerificationRejected;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

function makeAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function pendingSubmission(): StudentVerificationDocument
{
    $student = User::factory()->studentVerificationSubmitted()->create();

    return StudentVerificationDocument::factory()->for($student)->create();
}

describe('panel access', function () {
    it('keeps ordinary students out of the admin panel entirely', function () {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    });

    it('lets admin and governance committee staff in', function () {
        $this->actingAs(makeAdmin())->get('/admin')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'governance_committee']))->get('/admin')->assertOk();
    });

    it('sends guests to the panel login page', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
    });
});

describe('reviewing a submission', function () {
    it('lists pending submissions with the student\'s registered details', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->assertCanSeeTableRecords([$document])
            ->assertSee($document->user->name)
            ->assertSee($document->user->student_id);
    });

    it('approves a submission: verifies the student and notifies them', function () {
        $this->actingAs(makeAdmin());
        Notification::fake();
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)->callTableAction('approve', $document);

        $fresh = $document->user->fresh();
        expect($fresh->is_verified)->toBeTrue()
            ->and($fresh->student_verification_status)->toBe(User::STUDENT_VERIFICATION_VERIFIED)
            ->and($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_APPROVED)
            ->and($document->fresh()->reviewed_by)->not->toBeNull();

        Notification::assertSentTo($fresh, StudentVerificationApproved::class);
    });

    it('rejects a submission with a reason and notifies the student', function () {
        $this->actingAs(makeAdmin());
        Notification::fake();
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->callTableAction('reject', $document, data: ['reason' => 'The photo is too blurry to read the ID number.']);

        $fresh = $document->user->fresh();
        expect($fresh->is_verified)->toBeFalse()
            ->and($fresh->student_verification_status)->toBe(User::STUDENT_VERIFICATION_REJECTED)
            ->and($fresh->student_verification_rejection_reason)->toBe('The photo is too blurry to read the ID number.')
            ->and($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_REJECTED);

        Notification::assertSentTo($fresh, StudentVerificationRejected::class);
    });

    it('requires a reason to reject or request resubmission', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->callTableAction('reject', $document, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        expect($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_PENDING);
    });

    it('marks resubmission-required distinctly from an outright rejection', function () {
        $this->actingAs(makeAdmin());
        Notification::fake();
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->callTableAction('request_resubmission', $document, data: ['reason' => 'Please upload a clearer photo of the front of your card.']);

        expect($document->user->fresh()->student_verification_status)->toBe(User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED);
    });

    it('does not let admins act on a submission the student has already replaced', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();
        StudentVerificationDocument::factory()->for($document->user)->create(); // a newer submission

        Livewire::test(ListStudentVerificationDocuments::class)->callTableAction('approve', $document);

        expect($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_PENDING)
            ->and($document->user->fresh()->is_verified)->toBeFalse();
    });

    it('does not offer decision actions on an already-decided submission', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();
        $document->update(['status' => StudentVerificationDocument::STATUS_APPROVED]);

        Livewire::test(ListStudentVerificationDocuments::class)
            ->assertTableActionHidden('approve', $document)
            ->assertTableActionHidden('reject', $document);
    });
});
