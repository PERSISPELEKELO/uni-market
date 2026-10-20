<?php

use App\Models\StudentVerificationDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake(StudentVerificationDocument::DISK));

it('deletes the image 30 days after a decision, keeping the decision record', function () {
    Storage::disk(StudentVerificationDocument::DISK)->put('verification-documents/1/old.jpg', 'bytes');
    $old = StudentVerificationDocument::factory()->for(User::factory())->create([
        'file_path' => 'verification-documents/1/old.jpg',
        'status' => StudentVerificationDocument::STATUS_APPROVED,
        'reviewed_at' => now()->subDays(31),
    ]);

    $this->artisan('verification:prune-documents')->assertExitCode(0);

    expect($old->fresh()->file_deleted_at)->not->toBeNull()
        ->and($old->fresh()->status)->toBe(StudentVerificationDocument::STATUS_APPROVED)
        ->and($old->fresh()->reviewed_at)->not->toBeNull();

    Storage::disk(StudentVerificationDocument::DISK)->assertMissing('verification-documents/1/old.jpg');
});

it('leaves a recently-decided document\'s image alone', function () {
    Storage::disk(StudentVerificationDocument::DISK)->put('verification-documents/2/recent.jpg', 'bytes');
    $recent = StudentVerificationDocument::factory()->for(User::factory())->create([
        'file_path' => 'verification-documents/2/recent.jpg',
        'status' => StudentVerificationDocument::STATUS_APPROVED,
        'reviewed_at' => now()->subDays(10),
    ]);

    $this->artisan('verification:prune-documents')->assertExitCode(0);

    expect($recent->fresh()->file_deleted_at)->toBeNull();
    Storage::disk(StudentVerificationDocument::DISK)->assertExists('verification-documents/2/recent.jpg');
});

it('never deletes a still-pending submission\'s image', function () {
    Storage::disk(StudentVerificationDocument::DISK)->put('verification-documents/3/pending.jpg', 'bytes');
    $pending = StudentVerificationDocument::factory()->for(User::factory())->create([
        'file_path' => 'verification-documents/3/pending.jpg',
        'status' => StudentVerificationDocument::STATUS_PENDING,
        'reviewed_at' => null,
    ]);

    $this->artisan('verification:prune-documents')->assertExitCode(0);

    expect($pending->fresh()->file_deleted_at)->toBeNull();
    Storage::disk(StudentVerificationDocument::DISK)->assertExists('verification-documents/3/pending.jpg');
});

it('serves a 410 for a document whose image has been pruned', function () {
    $document = StudentVerificationDocument::factory()->for($user = User::factory()->create())->create([
        'status' => StudentVerificationDocument::STATUS_APPROVED,
        'reviewed_at' => now()->subDays(40),
        'file_deleted_at' => now(),
    ]);

    $this->actingAs($user)->get(route('verification.documents.show', $document))->assertStatus(410);
});
