<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    private const MAX_ATTEMPTS = 10;

    public string $first_name = '';

    public string $last_name = '';

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
            'first_name' => ['required', 'string', 'max:100', $this->meaningfulNameRule()],
            'last_name' => ['required', 'string', 'max:100', $this->meaningfulNameRule()],
            'email' => ['required', 'string', 'email:rfc,strict,filter', 'max:255', 'unique:users,email'],
            'student_id' => ['required', 'digits_between:1,10', 'unique:users,student_id'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()\-]{7,20}$/', 'unique:users,phone_number'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Rejects obviously invalid or random input while still allowing short, genuine names.
     * Letters (including accented/unicode), spaces, hyphens and apostrophes only, and not
     * just the same character repeated (e.g. "aaaaaa").
     */
    private function meaningfulNameRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $trimmed = trim((string) $value);

            if (! preg_match("/^\p{L}+(?:['\-\s]\p{L}+)*$/u", $trimmed)) {
                $fail('Please enter a valid name using letters only.');

                return;
            }

            $lettersOnly = preg_replace("/['\-\s]/u", '', $trimmed);

            if (preg_match('/^(.)\1*$/u', $lettersOnly)) {
                $fail('Please enter your real name.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'first_name.required' => 'Please enter your first name.',
            'last_name.required' => 'Please enter your last name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists. Try logging in instead.',
            'student_id.required' => 'Please enter your student ID number.',
            'student_id.digits_between' => 'Your student ID must contain only numbers, up to 10 digits long.',
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
        $this->resetErrorBag();

        $this->first_name = trim($this->first_name);
        $this->last_name = trim($this->last_name);
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
                'name' => trim("{$this->first_name} {$this->last_name}"),
                'email' => $this->email,
                'student_id' => $this->student_id,
                'phone_number' => $this->phone_number,
                'password' => $this->password,
                'role' => 'student',
                'is_verified' => false,
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

        // Sends the verification email; the "Official Student" badge is granted once the link is opened.
        // A mail-server problem must never block sign-up: the member can resend the email from the banner.
        $emailSent = true;

        try {
            event(new Registered($user));
        } catch (\Throwable $exception) {
            report($exception);
            $emailSent = false;
        }

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('listings.index')
            ->with('success', $emailSent
                ? 'Account created! We emailed you a link - confirm your address to earn the Official Student badge.'
                : 'Account created! We could not send your verification email just now - use "Verify now" below to try again.');
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('layouts.app', ['title' => 'Create your account - UniMarket']);
    }
}
