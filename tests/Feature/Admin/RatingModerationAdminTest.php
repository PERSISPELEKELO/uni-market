<?php

use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AppealWorkflowService;
use App\Services\RatingModerationService;
use App\Services\RatingService;

beforeEach(fn () => $this->withoutVite());

function adminRatedTransaction(): Rating
{
    $transaction = Transaction::factory()->create([
        'buyer_id' => User::factory(),
        'seller_id' => User::factory(),
        'status' => 'COMPLETED',
    ]);

    return app(RatingService::class)->rate($transaction, $transaction->buyer, 3, 'It was okay.');
}

it('lets an admin view a rating with no reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $rating = adminRatedTransaction();

    $this->actingAs($admin)->get("/admin/ratings/{$rating->id}")->assertOk();
});

it('lets an admin view a reported rating, including the report list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $reporter = User::factory()->create(['role' => 'student']);
    $rating = adminRatedTransaction();

    app(AppealWorkflowService::class)->submitAppeal($reporter, 'Rating', $rating->id, 'Spam: looks automated.');

    $this->actingAs($admin)->get("/admin/ratings/{$rating->id}")
        ->assertOk()
        ->assertSee($reporter->name)
        ->assertSee('Spam: looks automated.');
});

it('lets an admin view a Rating-type appeal, showing the underlying review', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $reporter = User::factory()->create(['role' => 'student']);
    $rating = adminRatedTransaction();

    $appeal = app(AppealWorkflowService::class)->submitAppeal($reporter, 'Rating', $rating->id, 'Harassment: rude comment.');

    $this->actingAs($admin)->get("/admin/appeals/{$appeal->id}")
        ->assertOk()
        ->assertSee($rating->rater->name)
        ->assertSee($rating->rated->name)
        ->assertSee('It was okay.');
});

it('lets an admin hide then restore a review from the admin panel, and reflects it on the profile', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $rating = adminRatedTransaction();
    $seller = $rating->rated;

    app(RatingModerationService::class)->hide($rating, $admin, 'Testing moderation from admin.');
    expect($seller->ratingsCount())->toBe(0);

    app(RatingModerationService::class)->restore($rating->fresh(), $admin);
    expect($seller->ratingsCount())->toBe(1);
});
