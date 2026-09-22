<?php

use App\Livewire\Account\StudentVerification;
use App\Models\StudentVerificationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
});

it('needs a login', function () {
    $this->get(route('verification.student.form'))->assertRedirect(route('login'));
});

it('tells a member with an unverified email to verify it first, and does not accept a submission', function () {
    $user = User::factory()->unverified()->pendingStudentVerification()->create();

    $this->actingAs($user)->get(route('verification.student.form'))
        ->assertOk()
        ->assertSee('Please verify your email address first.')
        ->assertDontSee('Submit for review');

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakePhoto('id-card.jpg'))
        ->call('submit')
        ->assertDispatched('notify', type: 'error');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('lets a member with a verified email submit their student id document', function () {
    $user = User::factory()->pendingStudentVerification()->create();

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakePhoto('id-card.jpg'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('notify', type: 'success');

    $document = StudentVerificationDocument::firstOrFail();

    expect($document->user_id)->toBe($user->id)
        ->and($document->status)->toBe(StudentVerificationDocument::STATUS_PENDING)
        ->and($document->original_filename)->toBe('id-card.jpg');

    Storage::disk('local')->assertExists($document->file_path);

    $fresh = $user->fresh();
    expect($fresh->student_verification_status)->toBe(User::STUDENT_VERIFICATION_PENDING)
        ->and($fresh->student_verification_submitted_at)->not->toBeNull();
});

it('never stores the document on the public disk', function () {
    Storage::fake('public');
    $user = User::factory()->pendingStudentVerification()->create();

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakePhoto('id-card.jpg'))
        ->call('submit');

    $document = StudentVerificationDocument::firstOrFail();

    Storage::disk('public')->assertMissing($document->file_path);
    Storage::disk('local')->assertExists($document->file_path);
});

it('requires a document and rejects the wrong file type or an oversized file', function () {
    $user = User::factory()->pendingStudentVerification()->create();
    $component = Livewire::actingAs($user)->test(StudentVerification::class);

    $component->call('submit')
        ->assertHasErrors('document')
        ->assertSee('Please choose your student ID document to upload.');

    $component->set('document', UploadedFile::fake()->create('id.exe', 100, 'application/octet-stream'))
        ->call('submit')
        ->assertHasErrors('document')
        ->assertSee('Please upload a JPG, PNG or PDF file.');

    $component->set('document', UploadedFile::fake()->create('id.pdf', 6000, 'application/pdf'))
        ->call('submit')
        ->assertHasErrors('document')
        ->assertSee('The file must be 5 MB or smaller.');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('accepts a pdf document', function () {
    $user = User::factory()->pendingStudentVerification()->create();

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors();

    expect(StudentVerificationDocument::count())->toBe(1);
});

it('does not allow a second submission while one is already pending', function () {
    $user = User::factory()->studentVerificationSubmitted()->create();

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakePhoto('id-card-2.jpg'))
        ->call('submit')
        ->assertDispatched('notify', type: 'error');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('does not allow a submission once the student is already verified', function () {
    $user = User::factory()->create(); // fully verified by default

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakePhoto('id-card.jpg'))
        ->call('submit')
        ->assertDispatched('notify', type: 'error');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('lets a member resubmit after rejection and keeps the rejected attempt in their history', function () {
    $user = User::factory()->pendingStudentVerification()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_REJECTED,
        'student_verification_rejection_reason' => 'The photo was too blurry to read.',
    ]);
    StudentVerificationDocument::factory()->for($user)->create(['status' => StudentVerificationDocument::STATUS_REJECTED]);

    $this->actingAs($user)->get(route('verification.student.form'))
        ->assertSee('Your verification was not approved.')
        ->assertSee('The photo was too blurry to read.');

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakePhoto('id-card-clear.jpg'))
        ->call('submit')
        ->assertHasNoErrors();

    expect(StudentVerificationDocument::count())->toBe(2)
        ->and($user->fresh()->student_verification_status)->toBe(User::STUDENT_VERIFICATION_PENDING)
        ->and($user->fresh()->student_verification_rejection_reason)->toBeNull();
});

it('limits how many documents can be submitted in an hour', function () {
    $user = User::factory()->pendingStudentVerification()->create();
    $component = Livewire::actingAs($user)->test(StudentVerification::class);

    foreach (range(1, 5) as $attempt) {
        $user->forceFill(['student_verification_status' => User::STUDENT_VERIFICATION_REJECTED])->save();
        $component->set('document', fakePhoto("id-{$attempt}.jpg"))->call('submit');
    }

    $user->forceFill(['student_verification_status' => User::STUDENT_VERIFICATION_REJECTED])->save();
    $component->set('document', fakePhoto('id-6.jpg'))->call('submit')
        ->assertHasErrors('document')
        ->assertSee('Too many submissions');

    expect(StudentVerificationDocument::count())->toBe(5);
});

describe('viewing a submitted document', function () {
    it('lets the owner view their own document', function () {
        $user = User::factory()->create();
        $document = StudentVerificationDocument::factory()->for($user)->create();
        Storage::disk('local')->put($document->file_path, 'fake-image-bytes');

        $this->actingAs($user)->get(route('verification.documents.show', $document))->assertOk();
    });

    it('lets admins and governance staff view any document', function () {
        $document = StudentVerificationDocument::factory()->create();
        Storage::disk('local')->put($document->file_path, 'fake-image-bytes');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('verification.documents.show', $document))->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'governance_committee']))
            ->get(route('verification.documents.show', $document))->assertOk();
    });

    it('never lets another student view someone else\'s document', function () {
        $document = StudentVerificationDocument::factory()->create();
        Storage::disk('local')->put($document->file_path, 'fake-image-bytes');

        $this->actingAs(User::factory()->create())
            ->get(route('verification.documents.show', $document))
            ->assertForbidden();
    });

    it('sends guests to log in', function () {
        $document = StudentVerificationDocument::factory()->create();

        $this->get(route('verification.documents.show', $document))->assertRedirect(route('login'));
    });
});
