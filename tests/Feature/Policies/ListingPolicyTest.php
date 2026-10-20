<?php

use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * The account-state table from the verification brief:
 * guest / unverified-email / email-verified-but-not-student-verified /
 * fully verified, each against browse, message+reserve+buy, and sell.
 * Browsing a listing's own policy (view) is covered by ListingBrowsingTest
 * already; this file is specifically the create (sell) gate.
 */
it('refuses to let an email-unverified student sell', function () {
    $user = User::factory()->unverified()->create();

    expect(Gate::forUser($user)->allows('create', Listing::class))->toBeFalse();
});

it('refuses to let an email-verified but not-yet-student-verified student sell', function () {
    $user = User::factory()->pendingStudentVerification()->create();

    expect($user->hasVerifiedEmail())->toBeTrue();
    expect(Gate::forUser($user)->allows('create', Listing::class))->toBeFalse();
});

it('refuses to let a student with a pending verification submission sell', function () {
    $user = User::factory()->studentVerificationSubmitted()->create();

    expect(Gate::forUser($user)->allows('create', Listing::class))->toBeFalse();
});

it('lets a fully verified student sell', function () {
    $user = User::factory()->create(); // the base factory state is already email+student verified

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->isStudentVerified())->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', Listing::class))->toBeTrue();
});

it('returns a 403 for a direct create-listing request from an unverified student, not just a hidden button', function () {
    $user = User::factory()->pendingStudentVerification()->create();

    expect(fn () => Gate::forUser($user)->authorize('create', Listing::class))
        ->toThrow(AuthorizationException::class);
});
