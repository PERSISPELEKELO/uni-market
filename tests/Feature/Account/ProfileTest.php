<?php

use App\Livewire\Account\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
});

it('needs a login', function () {
    $this->get(route('account'))->assertRedirect(route('login'));
});

it('shows the member their own details and verification status', function () {
    $user = User::factory()->create(['name' => 'Chileshe Mwansa', 'email' => 'chileshe@example.com', 'student_id' => '2024198273']);

    $this->actingAs($user)->get(route('account'))
        ->assertOk()
        ->assertSee('My account')
        ->assertSee('chileshe@example.com')
        ->assertSee('2024198273')
        ->assertSee('Official Student');
});

it('is linked from the account menu', function () {
    $this->actingAs(User::factory()->create())->get(route('listings.index'))->assertSee(route('account'), false)->assertSee('My account');
});

describe('updating details', function () {
    it('saves a new name and phone number', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', '  Mwamba Bwalya  ')
            ->set('phone_number', '+260971234567')
            ->call('updateProfile')
            ->assertHasNoErrors();

        expect($user->fresh()->name)->toBe('Mwamba Bwalya')->and($user->fresh()->phone_number)->toBe('+260971234567');
    });

    it('lets a member keep their own phone number but not take someone else\'s', function () {
        $user = User::factory()->create(['phone_number' => '+260971111111']);
        User::factory()->create(['phone_number' => '+260972222222']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'Same Person')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->set('phone_number', '+260972222222')
            ->call('updateProfile')
            ->assertHasErrors(['phone_number' => 'unique']);

        expect($user->fresh()->phone_number)->toBe('+260971111111');
    });

    it('validates the name and phone number', function () {
        Livewire::actingAs(User::factory()->create())->test(Profile::class)
            ->set('name', '')
            ->set('phone_number', 'call me')
            ->call('updateProfile')
            ->assertHasErrors(['name' => 'required', 'phone_number' => 'regex'])
            ->assertSee('Please enter your full name.');
    });

    it('stores a cleared phone number as null', function () {
        $user = User::factory()->create(['phone_number' => '+260971111111']);

        Livewire::actingAs($user)->test(Profile::class)->set('phone_number', '')->call('updateProfile')->assertHasNoErrors();

        expect($user->fresh()->phone_number)->toBeNull();
    });

    it('saves a bio, programme and business type without an arbitrary minimum length', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Profile::class)
            ->set('bio', 'New!')
            ->set('programme', 'BSc')
            ->set('business_type', 'Reseller')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        expect($fresh->bio)->toBe('New!')
            ->and($fresh->programme)->toBe('BSc')
            ->and($fresh->business_type)->toBe('Reseller');
    });

    it('still rejects a bio that is far too long', function () {
        Livewire::actingAs(User::factory()->create())->test(Profile::class)
            ->set('bio', str_repeat('a', 1001))
            ->call('updateProfile')
            ->assertHasErrors(['bio' => 'max']);
    });
});

describe('profile photo', function () {
    it('uploads a photo to the public disk and shows it instead of initials', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Profile::class)
            ->set('avatar', fakePhoto('me.png'))
            ->call('updateAvatar')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        expect($fresh->avatar_path)->not->toBeNull();
        Storage::disk('public')->assertExists($fresh->avatar_path);

        $this->actingAs($fresh)->get(route('account'))->assertSee($fresh->avatar_url, false);
    });

    it('rejects the wrong file type or an oversized photo', function () {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Profile::class);

        $component->set('avatar', UploadedFile::fake()->create('me.pdf', 100, 'application/pdf'))
            ->call('updateAvatar')
            ->assertHasErrors('avatar');

        $component->set('avatar', fakePhoto('me.png', 3000))
            ->call('updateAvatar')
            ->assertHasErrors('avatar');

        expect($user->fresh()->avatar_path)->toBeNull();
    });

    it('replaces the old photo file when a new one is uploaded', function () {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Profile::class);

        $component->set('avatar', fakePhoto('first.png'))->call('updateAvatar');
        $firstPath = $user->fresh()->avatar_path;

        $component->set('avatar', fakePhoto('second.png'))->call('updateAvatar');
        $secondPath = $user->fresh()->avatar_path;

        expect($secondPath)->not->toBe($firstPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    });

    it('lets a member remove their photo, falling back to initials', function () {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Profile::class)->set('avatar', fakePhoto('me.png'))->call('updateAvatar');
        $path = $user->fresh()->avatar_path;

        Livewire::actingAs($user->fresh())->test(Profile::class)->call('removeAvatar');

        expect($user->fresh()->avatar_path)->toBeNull();
        Storage::disk('public')->assertMissing($path);
    });

    it('shows the initial as a default avatar when no photo has been uploaded', function () {
        $user = User::factory()->create(['name' => 'Zainab Tembo']);

        $this->actingAs($user)->get(route('account'))->assertSee('>Z<', false);
    });
});

describe('changing the password', function () {
    it('changes the password when the current one is correct', function () {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'OldPassword1')
            ->set('new_password', 'BrandNew123')
            ->set('new_password_confirmation', 'BrandNew123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('current_password', '')
            ->assertSet('new_password', '');

        expect(Hash::check('BrandNew123', $user->fresh()->password))->toBeTrue();
    });

    it('refuses a wrong current password', function () {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'guess')
            ->set('new_password', 'BrandNew123')
            ->set('new_password_confirmation', 'BrandNew123')
            ->call('updatePassword')
            ->assertHasErrors('current_password')
            ->assertSee('That is not your current password.');

        expect(Hash::check('OldPassword1', $user->fresh()->password))->toBeTrue();
    });

    it('enforces password rules and requires a different password', function (string $new, string $confirmation, string $message) {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'OldPassword1')
            ->set('new_password', $new)
            ->set('new_password_confirmation', $confirmation)
            ->call('updatePassword')
            ->assertHasErrors('new_password')
            ->assertSee($message);

        expect(Hash::check('OldPassword1', $user->fresh()->password))->toBeTrue();
    })->with([
        'too short' => ['abc12', 'abc12', 'at least 8 characters'],
        'no number' => ['onlyletters', 'onlyletters', 'at least one number'],
        'mismatch' => ['BrandNew123', 'BrandNew124', 'do not match'],
        'unchanged' => ['OldPassword1', 'OldPassword1', 'must be different'],
    ]);

    it('locks out repeated wrong current-password guesses', function () {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);

        $component = Livewire::actingAs($user)->test(Profile::class)
            ->set('new_password', 'BrandNew123')
            ->set('new_password_confirmation', 'BrandNew123');

        foreach (range(1, 5) as $attempt) {
            $component->set('current_password', 'wrong'.$attempt)->call('updatePassword');
        }

        $component->set('current_password', 'OldPassword1')->call('updatePassword')
            ->assertHasErrors('current_password')
            ->assertSee('Too many attempts');

        expect(Hash::check('OldPassword1', $user->fresh()->password))->toBeTrue();
    });
});

it('only ever edits the signed-in member', function () {
    $victim = User::factory()->create(['name' => 'Victim Name']);
    $attacker = User::factory()->create();

    Livewire::actingAs($attacker)->test(Profile::class)->set('name', 'Attacker Renamed')->call('updateProfile');

    expect($victim->fresh()->name)->toBe('Victim Name')->and($attacker->fresh()->name)->toBe('Attacker Renamed');
});
