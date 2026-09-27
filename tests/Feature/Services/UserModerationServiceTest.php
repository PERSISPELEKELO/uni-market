<?php

use App\Livewire\Auth\Login;
use App\Models\AuditLog;
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
