<?php

namespace App\Livewire\Auth;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class Login extends Component
{
    private const MAX_ATTEMPTS = 5;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }

    public function login()
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate();

        $throttleKey = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->password = '';
            $this->addError('credentials', "Too many login attempts. Please try again in {$seconds} seconds.");

            return null;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            $this->password = '';
            $this->addError('credentials', 'The email or password you entered is incorrect.');

            return null;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        app(AuditLoggerService::class)->log(
            'USER_LOGIN',
            'User',
            Auth::id(),
            ['email' => $this->email],
            Auth::user()
        );

        return redirect()->intended(route('listings.index'));
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('layouts.app', ['title' => 'Log in - UniMarket']);
    }

    private function throttleKey(): string
    {
        return Str::transliterate($this->email.'|'.request()->ip());
    }
}
