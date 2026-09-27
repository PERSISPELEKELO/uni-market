<?php

use App\Enums\AppealStatus;
use App\Models\Appeal;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AppealWorkflowService;
use DomainException;

beforeEach(fn () => $this->service = app(AppealWorkflowService::class));

function makeAppeal(string $status = AppealStatus::PENDING->value): Appeal
{
    return Appeal::create([
        'user_id' => User::factory()->create()->id,
        'target_type' => 'Listing',
        'target_id' => '1',
        'reason' => 'Testing.',
        'status' => $status,
    ]);
}

it('submits an appeal for a student', function () {
    $user = User::factory()->create();

    $appeal = $this->service->submitAppeal($user, 'Listing', '42', 'My listing was suspended unfairly.');

    expect($appeal->status)->toBe(AppealStatus::PENDING)
        ->and(AuditLog::where('action', 'APPEAL_SUBMITTED')->where('target_id', $appeal->id)->exists())->toBeTrue();
});

it('rejects an empty appeal reason', function () {
    $user = User::factory()->create();

    expect(fn () => $this->service->submitAppeal($user, 'Listing', '42', '   '))
        ->toThrow(InvalidArgumentException::class);
});

it('moves a pending appeal into review', function () {
    $appeal = makeAppeal();
    $actor = User::factory()->create(['role' => 'admin']);

    $this->service->startReview($appeal, $actor);

    expect($appeal->refresh()->status)->toBe(AppealStatus::UNDER_REVIEW);
    expect(AuditLog::where('action', 'APPEAL_REVIEW_STARTED')->where('target_id', $appeal->id)->exists())->toBeTrue();
});

it('refuses to start review on an appeal that is not pending', function () {
    $appeal = makeAppeal(AppealStatus::UNDER_REVIEW->value);
    $actor = User::factory()->create(['role' => 'admin']);

    expect(fn () => $this->service->startReview($appeal, $actor))
        ->toThrow(DomainException::class, "Expected 'PENDING'");
});

it('decides an appeal under review as upheld or overturned', function (string $outcome) {
    $appeal = makeAppeal(AppealStatus::UNDER_REVIEW->value);
    $actor = User::factory()->create(['role' => 'admin']);

    $this->service->decideAppeal($appeal, $outcome, 'Reviewed the evidence.', $actor);

    $appeal->refresh();

    expect($appeal->status->value)->toBe($outcome)
        ->and($appeal->governance_notes)->toBe('Reviewed the evidence.')
        ->and($appeal->resolved_at)->not->toBeNull();

    expect(AuditLog::where('action', 'APPEAL_DECIDED')->where('target_id', $appeal->id)->exists())->toBeTrue();
})->with([
    'upheld' => [AppealStatus::UPHELD->value],
    'overturned' => [AppealStatus::OVERTURNED->value],
]);

it('refuses to decide an appeal that is not under review', function () {
    $appeal = makeAppeal(AppealStatus::PENDING->value);
    $actor = User::factory()->create(['role' => 'admin']);

    expect(fn () => $this->service->decideAppeal($appeal, AppealStatus::UPHELD->value, 'notes', $actor))
        ->toThrow(DomainException::class, "Expected 'UNDER_REVIEW'");
});

it('refuses an invalid decision outcome', function () {
    $appeal = makeAppeal(AppealStatus::UNDER_REVIEW->value);
    $actor = User::factory()->create(['role' => 'admin']);

    expect(fn () => $this->service->decideAppeal($appeal, 'MAYBE', 'notes', $actor))
        ->toThrow(InvalidArgumentException::class);
});
