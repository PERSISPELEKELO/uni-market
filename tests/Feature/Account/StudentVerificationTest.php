<?php

use App\Livewire\Account\StudentVerification;
use App\Models\StudentVerificationDocument;
use App\Models\User;
use App\Notifications\NewStudentVerificationSubmission;
use App\Notifications\StudentVerificationSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
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
        ->set('document', fakeIdPhoto('id-card.jpg'))
        ->set('confirmedOwnCard', true)
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
        ->set('document', fakeIdPhoto('id-card.jpg'))
        ->set('confirmedOwnCard', true)
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
        ->assertSee('Please choose your student ID photo to upload.');

    $component->set('document', UploadedFile::fake()->create('id.exe', 100, 'application/octet-stream'))
        ->call('submit')
        ->assertHasErrors('document')
        ->assertSee('Please upload a JPG, PNG or WebP photo.');

    $component->set('document', UploadedFile::fake()->create('id.jpg', 6000, 'image/jpeg'))
        ->call('submit')
        ->assertHasErrors('document')
        ->assertSee('The file must be 5 MB or smaller.');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('rejects a pdf document', function () {
    $user = User::factory()->pendingStudentVerification()->create();

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'))
        ->set('confirmedOwnCard', true)
        ->call('submit')
        ->assertHasErrors('document')
        ->assertSee('Please upload a JPG, PNG or WebP photo.');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('rejects a photo smaller than the minimum dimensions', function () {
    $user = User::factory()->pendingStudentVerification()->create();

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakePhoto('tiny.png'))
        ->set('confirmedOwnCard', true)
        ->call('submit')
        ->assertHasErrors('document')
        ->assertSee('Your photo is too small');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('requires confirming this is the student\'s own id card', function () {
    $user = User::factory()->pendingStudentVerification()->create();

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', fakeIdPhoto())
        ->call('submit')
        ->assertHasErrors('confirmedOwnCard')
        ->assertSee('Please confirm this is your own student ID card.');

    expect(StudentVerificationDocument::count())->toBe(0);
});

it('strips exif metadata, including gps location, from the stored photo', function () {
    $user = User::factory()->pendingStudentVerification()->create();
    $upload = fakeIdPhotoWithExif();

    // Sanity-check the fixture itself genuinely carries an EXIF segment, so
    // a pass below can only mean the storage pipeline actually stripped it -
    // not that the fixture never had any to begin with. (The exif extension
    // isn't available in every environment, so this checks for the raw
    // "Exif\0\0" marker rather than calling exif_read_data().)
    expect(file_get_contents($upload->getRealPath()))->toContain("Exif\0\0");

    Livewire::actingAs($user)->test(StudentVerification::class)
        ->set('document', $upload)
        ->set('confirmedOwnCard', true)
        ->call('submit')
        ->assertHasNoErrors();

    $storedBytes = Storage::disk('local')->get(StudentVerificationDocument::firstOrFail()->file_path);

    expect($storedBytes)->not->toContain("Exif\0\0");
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
        ->set('document', fakeIdPhoto('id-card-clear.jpg'))
        ->set('confirmedOwnCard', true)
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
        $component->set('document', fakeIdPhoto("id-{$attempt}.jpg"))
            ->set('confirmedOwnCard', true)
            ->call('submit');
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

describe('notifications on submission', function () {
    it('tells the student their document was received and is under review', function () {
        Notification::fake();
        $user = User::factory()->pendingStudentVerification()->create();

        Livewire::actingAs($user)->test(StudentVerification::class)
            ->set('document', fakeIdPhoto())
            ->set('confirmedOwnCard', true)
            ->call('submit');

        Notification::assertSentTo($user, StudentVerificationSubmitted::class);
    });

    it('alerts admin and governance staff when no one has opened the review queue recently', function () {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $governance = User::factory()->create(['role' => 'governance_committee']);
        $user = User::factory()->pendingStudentVerification()->create();

        Livewire::actingAs($user)->test(StudentVerification::class)
            ->set('document', fakeIdPhoto())
            ->set('confirmedOwnCard', true)
            ->call('submit');

        Notification::assertSentTo($admin, NewStudentVerificationSubmission::class);
        Notification::assertSentTo($governance, NewStudentVerificationSubmission::class);
    });

    it('does not alert staff when an admin opened the queue within the last hour', function () {
        Notification::fake();
        Cache::put(StudentVerification::QUEUE_LAST_VIEWED_CACHE_KEY, now(), now()->addHour());
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->pendingStudentVerification()->create();

        Livewire::actingAs($user)->test(StudentVerification::class)
            ->set('document', fakeIdPhoto())
            ->set('confirmedOwnCard', true)
            ->call('submit');

        Notification::assertNothingSentTo($admin);
    });

    it('never sends more than one staff alert per hour, even with several submissions', function () {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([1, 2] as $attempt) {
            $user = User::factory()->pendingStudentVerification()->create();

            Livewire::actingAs($user)->test(StudentVerification::class)
                ->set('document', fakeIdPhoto("id-{$attempt}.jpg"))
                ->set('confirmedOwnCard', true)
                ->call('submit');
        }

        Notification::assertSentToTimes($admin, NewStudentVerificationSubmission::class, 1);
    });
});
