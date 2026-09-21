<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    private const MAX_ATTEMPTS = 10;

    public string $name = '';

    public string $email = '';

    public string $student_id = '';

    public ?string $phone_number = null;

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'student_id' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/', 'unique:users,student_id'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()\-]{7,20}$/', 'unique:users,phone_number'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'name.min' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists. Try logging in instead.',
            'student_id.required' => 'Please enter your student ID number.',
            'student_id.regex' => 'Your student ID can only contain letters, numbers, dashes and slashes.',
            'student_id.unique' => 'This student ID is already registered.',
            'phone_number.regex' => 'Please enter a valid phone number, for example +260971234567.',
            'phone_number.unique' => 'This phone number is already registered.',
            'password.required' => 'Please choose a password.',
            'password.min' => 'Your password must be at least 8 characters long.',
            'password.letters' => 'Your password must include at least one letter.',
            'password.numbers' => 'Your password must include at least one number.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }

    public function register()
    {
        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));
        $this->student_id = trim($this->student_id);
        $this->phone_number = filled(trim((string) $this->phone_number)) ? trim((string) $this->phone_number) : null;

        $throttleKey = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('form', "Too many sign-up attempts. Please try again in {$seconds} seconds.");

            return null;
        }

        RateLimiter::hit($throttleKey);

        $this->validate();

        try {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'student_id' => $this->student_id,
                'phone_number' => $this->phone_number,
                'password' => $this->password,
                'role' => 'student',
                'is_verified' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->addError('email', 'An account with these details already exists. Try logging in instead.');

            return null;
        }

        app(AuditLoggerService::class)->log(
            'USER_REGISTERED',
            'User',
            $user->id,
            [
                'email' => $user->email,
                'is_verified' => $user->is_verified,
            ],
            $user
        );

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('listings.index')
            ->with('success', 'Account created successfully! Welcome to UniMarket.');
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('layouts.app', ['title' => 'Create your account - UniMarket']);
    }
}
