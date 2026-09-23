<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

function validRegistration(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Chileshe',
        'last_name' => 'Mwansa',
        'email' => 'chileshe@example.com',
        'student_id' => '2024198273',
        'phone_number' => '+260971234567',
        'password' => 'Sunshine123',
        'password_confirmation' => 'Sunshine123',
    ], $overrides);
}

function fillRegistration(array $data)
{
    $component = Livewire::test(Register::class);

    foreach ($data as $field => $value) {
        $component->set($field, $value);
    }

    return $component->call('register');
}

it('shows the registration page to guests', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account')
        ->assertSee('First name')
        ->assertSee('Last name')
        ->assertSee('Student ID number');
});

it('registers a student, combines first and last name, hashes the password and signs them in', function () {
    fillRegistration(validRegistration())
        ->assertHasNoErrors()
        ->assertRedirect(route('listings.index'));

    $user = User::where('email', 'chileshe@example.com')->firstOrFail();

    expect($user->name)->toBe('Chileshe Mwansa')
        ->and($user->role)->toBe('student')
        ->and($user->is_verified)->toBeFalse()
        ->and($user->password)->not->toBe('Sunshine123')
        ->and(Hash::check('Sunshine123', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('does not allow required fields to be empty', function () {
    Livewire::test(Register::class)
        ->call('register')
        ->assertHasErrors(['first_name' => 'required', 'last_name' => 'required', 'email' => 'required', 'student_id' => 'required', 'password' => 'required'])
        ->assertSee('Please enter your first name.')
        ->assertSee('Please enter your last name.')
        ->assertSee('Please enter your email address.')
        ->assertSee('Please enter your student ID number.')
        ->assertSee('Please choose a password.');

    expect(User::count())->toBe(0);
});

it('accepts short but genuine names', function (string $first, string $last) {
    fillRegistration(validRegistration(['first_name' => $first, 'last_name' => $last, 'email' => 'short@example.com', 'student_id' => '2024000099']))
        ->assertHasNoErrors();

    expect(User::where('email', 'short@example.com')->value('name'))->toBe("{$first} {$last}");
})->with([
    'three letters' => ['Sam', 'Lee'],
    'hyphenated and apostrophe' => ['Mary-Jane', "O'Brien"],
]);

it('rejects names containing digits, symbols or repeated gibberish characters', function (string $first, string $last, string $field) {
    fillRegistration(validRegistration(['first_name' => $first, 'last_name' => $last]))
        ->assertHasErrors([$field]);

    expect(User::count())->toBe(0);
})->with([
    'digits in first name' => ['J0hn123', 'Banda', 'first_name'],
    'symbols in last name' => ['Chileshe', '<script>alert(1)</script>', 'last_name'],
    'repeated single character' => ['aaaaaaa', 'Banda', 'first_name'],
]);

it('rejects an invalid email address', function () {
    fillRegistration(validRegistration(['email' => 'not-an-email']))
        ->assertHasErrors(['email' => 'email'])
        ->assertSee('Please enter a valid email address.');
});

it('rejects a duplicate email regardless of letter case', function () {
    User::factory()->create(['email' => 'chileshe@example.com']);

    fillRegistration(validRegistration(['email' => 'Chileshe@Example.com']))
        ->assertHasErrors(['email' => 'unique'])
        ->assertSee('An account with this email already exists');

    expect(User::count())->toBe(1);
});

it('rejects a duplicate student id and a duplicate phone number', function () {
    User::factory()->create(['student_id' => '2024198273', 'phone_number' => '+260971234567']);

    fillRegistration(validRegistration(['email' => 'other@example.com']))
        ->assertHasErrors(['student_id' => 'unique', 'phone_number' => 'unique']);

    expect(User::count())->toBe(1);
});

it('stores a blank phone number as null so it cannot clash with other blanks', function () {
    fillRegistration(validRegistration(['phone_number' => '  ']))->assertHasNoErrors();
    fillRegistration(validRegistration(['email' => 'second@example.com', 'student_id' => '2024000001', 'phone_number' => '']))->assertHasNoErrors();

    expect(User::whereNull('phone_number')->count())->toBe(2);
});

it('enforces password strength and confirmation', function (string $password, string $confirmation, string $message) {
    fillRegistration(validRegistration(['password' => $password, 'password_confirmation' => $confirmation]))
        ->assertHasErrors('password')
        ->assertSee($message);

    expect(User::count())->toBe(0);
})->with([
    'too short' => ['abc12', 'abc12', 'at least 8 characters'],
    'no number' => ['onlyletters', 'onlyletters', 'at least one number'],
    'no letter' => ['12345678', '12345678', 'at least one letter'],
    'does not match' => ['Sunshine123', 'Sunshine124', 'do not match'],
]);

it('rejects a malformed phone number', function () {
    fillRegistration(validRegistration(['phone_number' => 'call me']))
        ->assertHasErrors(['phone_number' => 'regex']);
});

it('accepts a student id of up to 10 digits', function (string $studentId) {
    fillRegistration(validRegistration(['student_id' => $studentId]))->assertHasNoErrors('student_id');

    expect(User::where('student_id', $studentId)->exists())->toBeTrue();
})->with([
    'exactly 10 digits' => ['1234567890'],
    'short numeric id' => ['12345'],
]);

it('rejects a student id that is not all-digit or is longer than 10 digits', function (string $studentId) {
    fillRegistration(validRegistration(['student_id' => $studentId]))
        ->assertHasErrors(['student_id' => 'digits_between'])
        ->assertSee('Your student ID must contain only numbers, up to 10 digits long.');

    expect(User::count())->toBe(0);
})->with([
    'more than 10 digits' => ['202412345678'],
    'letters mixed in' => ['12345ABC89'],
    'letters only' => ['ABCDEFGHIJ'],
    'contains a dash' => ['1234-56789'],
    'html/script injection attempt' => ['<script>alert(1)</script>'],
]);
