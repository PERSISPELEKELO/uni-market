<?php

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

describe('the Edit User page', function () {
    it('opens for another user without error and disables the role field for the admin\'s own record', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'student']);

        // Someone else's record: the role field must be editable.
        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $other->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldIsEnabled('role');

        // The admin's own record: the role field must be disabled.
        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldIsDisabled('role');
    });

    it('lets an admin change another user\'s role', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'student']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['role' => 'governance_committee'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($target->fresh()->role)->toBe('governance_committee');
    });
});
