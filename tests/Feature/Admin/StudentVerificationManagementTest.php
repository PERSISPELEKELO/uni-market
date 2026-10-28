<?php

use App\Filament\Resources\StudentVerificationDocumentResource\Pages\ListStudentVerificationDocuments;
use App\Filament\Resources\StudentVerificationDocumentResource\Pages\ViewSubmission;
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

        Livewire::test(ListStudentVerificationDocuments::class)->callTableAction('approve', $document, data: [
            'checklist_front_side' => true,
            'checklist_name_matches' => true,
            'checklist_number_matches' => true,
            'checklist_photo_clear' => true,
        ]);

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
            ->callTableAction('reject', $document, data: ['reason_code' => 'blurry_or_unclear']);

        $fresh = $document->user->fresh();
        expect($fresh->is_verified)->toBeFalse()
            ->and($fresh->student_verification_status)->toBe(User::STUDENT_VERIFICATION_REJECTED)
            ->and($fresh->student_verification_rejection_reason)->toBe(StudentVerificationDocument::REJECTION_REASONS['blurry_or_unclear'])
            ->and($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_REJECTED);

        Notification::assertSentTo($fresh, StudentVerificationRejected::class);
    });

    it('requires a reason to reject or request resubmission', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->callTableAction('reject', $document, data: ['reason_code' => ''])
            ->assertHasTableActionErrors(['reason_code' => 'required']);

        expect($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_PENDING);
    });

    it('requires a written detail when the reason is "other"', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->callTableAction('reject', $document, data: ['reason_code' => 'other', 'note' => ''])
            ->assertHasTableActionErrors(['note' => 'required_if']);

        expect($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_PENDING);
    });

    it('marks resubmission-required distinctly from an outright rejection', function () {
        $this->actingAs(makeAdmin());
        Notification::fake();
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->callTableAction('request_resubmission', $document, data: ['reason_code' => 'wrong_side']);

        expect($document->user->fresh()->student_verification_status)->toBe(User::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED);
    });

    it('requires the full checklist before approving', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();

        Livewire::test(ListStudentVerificationDocuments::class)
            ->callTableAction('approve', $document, data: [
                'checklist_front_side' => true,
                'checklist_name_matches' => true,
                'checklist_number_matches' => false,
                'checklist_photo_clear' => true,
            ])
            ->assertHasTableActionErrors(['checklist_number_matches' => 'accepted']);

        expect($document->fresh()->status)->toBe(StudentVerificationDocument::STATUS_PENDING);
    });

    it('does not let an admin review their own account\'s submission', function () {
        $admin = makeAdmin();
        $document = StudentVerificationDocument::factory()->for($admin)->create();

        $this->actingAs($admin);

        Livewire::test(ListStudentVerificationDocuments::class)
            ->assertTableActionHidden('approve', $document)
            ->assertTableActionHidden('reject', $document)
            ->assertTableActionHidden('request_resubmission', $document);
    });

    it('does not let admins act on a submission the student has already replaced', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();
        StudentVerificationDocument::factory()->for($document->user)->create(); // a newer submission

        Livewire::test(ListStudentVerificationDocuments::class)->callTableAction('approve', $document, data: [
            'checklist_front_side' => true,
            'checklist_name_matches' => true,
            'checklist_number_matches' => true,
            'checklist_photo_clear' => true,
        ]);

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

describe('the side-by-side review page', function () {
    it('shows the registered details and submission history next to the document', function () {
        $this->actingAs(makeAdmin());
        $document = pendingSubmission();
        $earlier = StudentVerificationDocument::factory()->for($document->user)->rejected()->create(['submitted_at' => now()->subDays(3)]);

        Livewire::test(ViewSubmission::class, ['record' => $document->getRouteKey()])
            ->assertOk()
            ->assertSee($document->user->name)
            ->assertSee($document->user->student_id)
            ->assertSee($document->user->email);

        expect(Livewire::test(ViewSubmission::class, ['record' => $document->getRouteKey()])->instance()->history()->pluck('id'))
            ->toContain($earlier->id)
            ->not->toContain($document->id);
    });

    it('still lets an admin view (but not decide on) their own submission', function () {
        $admin = makeAdmin();
        $document = StudentVerificationDocument::factory()->for($admin)->create();

        $this->actingAs($admin);

        Livewire::test(ViewSubmission::class, ['record' => $document->getRouteKey()])->assertOk();
    });
});
