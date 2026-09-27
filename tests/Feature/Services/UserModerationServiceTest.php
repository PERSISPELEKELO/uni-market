<?php

use App\Livewire\Auth\Login;
use App\Models\Appeal;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Rating;
use App\Models\StudentVerificationDocument;
use App\Models\Transaction;
use App\Models\User;
use App\Services\UserModerationService;

beforeEach(function () {
    $this->service = app(UserModerationService::class);
    $this->withoutVite();
});

it('suspends a user and records who did it and why', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'student']);

    $this->service->suspend($target, $admin, 'Repeated no-shows reported by three buyers.');

    $target->refresh();

    expect($target->isSuspended())->toBeTrue()
        ->and($target->suspended_by)->toBe($admin->id)
        ->and($target->suspension_reason)->toBe('Repeated no-shows reported by three buyers.');

    expect(AuditLog::where('action', 'USER_SUSPENDED')->where('target_id', $target->id)->exists())->toBeTrue();
});

it('reinstates a suspended user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'student', 'suspended_at' => now(), 'suspension_reason' => 'test']);

    $this->service->reinstate($target, $admin);

    $target->refresh();

    expect($target->isSuspended())->toBeFalse()
        ->and($target->suspended_by)->toBeNull()
        ->and($target->suspension_reason)->toBeNull();

    expect(AuditLog::where('action', 'USER_REINSTATED')->where('target_id', $target->id)->exists())->toBeTrue();
});

it('refuses to suspend your own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    expect(fn () => $this->service->suspend($admin, $admin, 'test'))
        ->toThrow(InvalidArgumentException::class, 'cannot suspend your own account');
});

it('refuses to let a non-admin suspend another admin', function () {
    $governance = User::factory()->create(['role' => 'governance_committee']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);

    expect(fn () => $this->service->suspend($otherAdmin, $governance, 'test'))
        ->toThrow(InvalidArgumentException::class, 'Only an admin');
});

it('refuses to suspend an already-suspended account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'student', 'suspended_at' => now()]);

    expect(fn () => $this->service->suspend($target, $admin, 'test'))
        ->toThrow(InvalidArgumentException::class, 'already suspended');
});

it('refuses to reinstate an account that is not suspended', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'student']);

    expect(fn () => $this->service->reinstate($target, $admin))
        ->toThrow(InvalidArgumentException::class, 'not suspended');
});

it('blocks login for a suspended student', function () {
    $user = User::factory()->create(['role' => 'student', 'suspended_at' => now()]);

    Livewire\Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('credentials');

    $this->assertGuest();
});

it('signs out a suspended user mid-session via the auth middleware', function () {
    $user = User::factory()->create(['role' => 'student']);

    $this->actingAs($user)->get(route('account'))->assertOk();

    $user->update(['suspended_at' => now()]);

    $this->get(route('account'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('denies a suspended admin access to the admin panel', function () {
    $admin = User::factory()->create(['role' => 'admin', 'suspended_at' => now()]);

    expect($admin->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

describe('deleting an account', function () {
    it('permanently deletes an account with no marketplace history, recording who and what before it goes', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'student', 'name' => 'Clean Account', 'email' => 'clean@example.com']);
        $targetId = $target->id;

        $this->service->delete($target, $admin);

        expect(User::find($targetId))->toBeNull();

        $log = AuditLog::where('action', 'USER_DELETED')->where('target_id', $targetId)->first();
        expect($log)->not->toBeNull()
            ->and($log->payload['name'])->toBe('Clean Account')
            ->and($log->payload['email'])->toBe('clean@example.com');
    });

    it('refuses to delete your own account', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        expect(fn () => $this->service->delete($admin, $admin))
            ->toThrow(InvalidArgumentException::class, 'cannot delete your own account');

        expect(User::find($admin->id))->not->toBeNull();
    });

    it('refuses to let a non-admin delete another admin', function () {
        $governance = User::factory()->create(['role' => 'governance_committee']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        expect(fn () => $this->service->delete($otherAdmin, $governance))
            ->toThrow(InvalidArgumentException::class, 'Only an admin');

        expect(User::find($otherAdmin->id))->not->toBeNull();
    });

    it('refuses to delete an account with any real marketplace history', function (callable $giveHistory) {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'student']);

        $giveHistory($target);

        expect(fn () => $this->service->delete($target, $admin))
            ->toThrow(InvalidArgumentException::class, 'marketplace history');

        expect(User::find($target->id))->not->toBeNull();
    })->with([
        'has a listing' => [fn (User $target) => Listing::factory()->create(['user_id' => $target->id])],
        'bought something' => [fn (User $target) => Transaction::factory()->create(['buyer_id' => $target->id])],
        'sold something' => [fn (User $target) => Transaction::factory()->create(['seller_id' => $target->id])],
        'raised a dispute' => [function (User $target) {
            $tx = Transaction::factory()->create(['buyer_id' => $target->id]);
            Dispute::create(['transaction_id' => $tx->id, 'raised_by' => $target->id, 'reason' => 'test', 'status' => 'open']);
        }],
        'filed an appeal' => [fn (User $target) => Appeal::factory()->create(['user_id' => $target->id])],
        'sent a message' => [fn (User $target) => Message::factory()->create(['sender_id' => $target->id])],
        'received a message' => [fn (User $target) => Message::factory()->create(['receiver_id' => $target->id])],
        'gave a rating' => [fn (User $target) => Rating::factory()->create(['rater_id' => $target->id])],
        'received a rating' => [fn (User $target) => Rating::factory()->create(['rated_id' => $target->id])],
        'submitted a verification document' => [fn (User $target) => StudentVerificationDocument::factory()->create(['user_id' => $target->id])],
    ]);
});
