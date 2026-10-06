<?php

use App\Enums\AppealStatus;
use App\Livewire\Appeals\MyAppeals;
use App\Models\Appeal;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('needs a login', function () {
    $this->get(route('appeals.mine'))->assertRedirect(route('login'));
});

it('shows an empty state when the student has never appealed anything', function () {
    $this->actingAs(User::factory()->create())->get(route('appeals.mine'))
        ->assertOk()
        ->assertSee('No appeals submitted');
});

it('lets a student with a rejected verification appeal it', function () {
    $user = User::factory()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_REJECTED,
        'student_verification_rejection_reason' => 'Document was unreadable.',
    ]);

    Livewire::actingAs($user)->test(MyAppeals::class)
        ->assertSee('Your student verification was rejected.')
        ->set('reason', 'The photo was actually clear, please take another look.')
        ->call('submitVerificationAppeal')
        ->assertHasNoErrors();

    $appeal = Appeal::where('user_id', $user->id)->first();
    expect($appeal)->not->toBeNull()
        ->and($appeal->target_type)->toBe('User')
        ->and($appeal->target_id)->toBe((string) $user->id)
        ->and($appeal->status)->toBe(AppealStatus::PENDING);
});

it('refuses a verification appeal when verification was not actually rejected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(MyAppeals::class)
        ->set('reason', 'Please reconsider my verification.')
        ->call('submitVerificationAppeal');

    expect(Appeal::where('user_id', $user->id)->count())->toBe(0);
});

it('lets a seller appeal a suspended listing they own', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->suspended()->for($seller, 'seller')->create(['title' => 'Suspended Item']);

    Livewire::actingAs($seller)->test(MyAppeals::class)
        ->assertSee('Suspended Item')
        ->set('reason', 'This listing did not break any rules.')
        ->call('submitListingAppeal', $listing->id)
        ->assertHasNoErrors();

    $appeal = Appeal::where('user_id', $seller->id)->first();
    expect($appeal)->not->toBeNull()
        ->and($appeal->target_type)->toBe('Listing')
        ->and($appeal->target_id)->toBe((string) $listing->id);
});

it('never lets a student appeal someone else\'s suspended listing', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $listing = Listing::factory()->suspended()->for($owner, 'seller')->create();

    Livewire::actingAs($attacker)->test(MyAppeals::class)
        ->set('reason', 'Not my listing, trying anyway.')
        ->call('submitListingAppeal', $listing->id)
        ->assertHasNoErrors();

    expect(Appeal::count())->toBe(0);
});

it('blocks a second appeal while one is already pending or under review', function () {
    $user = User::factory()->create([
        'student_verification_status' => User::STUDENT_VERIFICATION_REJECTED,
    ]);

    Appeal::create([
        'user_id' => $user->id,
        'target_type' => 'User',
        'target_id' => (string) $user->id,
        'reason' => 'First appeal.',
        'status' => AppealStatus::UNDER_REVIEW,
    ]);

    Livewire::actingAs($user)->test(MyAppeals::class)
        ->set('reason', 'Trying to appeal again while one is pending.')
        ->call('submitVerificationAppeal');

    expect(Appeal::where('user_id', $user->id)->count())->toBe(1);
});

it('validates that a reason is given and long enough', function () {
    $user = User::factory()->create(['student_verification_status' => User::STUDENT_VERIFICATION_REJECTED]);

    Livewire::actingAs($user)->test(MyAppeals::class)
        ->set('reason', 'too short')
        ->call('submitVerificationAppeal')
        ->assertHasErrors(['reason' => 'min']);
});

it('shows the outcome of a previously decided appeal', function () {
    $user = User::factory()->create();

    Appeal::create([
        'user_id' => $user->id,
        'target_type' => 'Listing',
        'target_id' => '999',
        'reason' => 'Please restore my listing.',
        'status' => AppealStatus::OVERTURNED,
        'governance_notes' => 'Reviewed and restored.',
    ]);

    $this->actingAs($user)->get(route('appeals.mine'))
        ->assertOk()
        ->assertSee('Overturned')
        ->assertSee('Reviewed and restored.');
});

it('only ever shows the signed-in student\'s own appeals', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Appeal::create([
        'user_id' => $owner->id,
        'target_type' => 'Listing',
        'target_id' => '1',
        'reason' => 'My own appeal.',
        'status' => AppealStatus::PENDING,
    ]);

    $this->actingAs($other)->get(route('appeals.mine'))
        ->assertOk()
        ->assertDontSee('My own appeal.');
});
