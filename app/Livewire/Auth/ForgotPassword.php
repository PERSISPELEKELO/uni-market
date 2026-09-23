<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class ForgotPassword extends Component
{
    private const MAX_REQUESTS_PER_MINUTE = 5;

    public string $email = '';

    public bool $sent = false;

    public function sendLink(): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate(
            ['email' => ['required', 'string', 'email:rfc,strict,filter', 'max:255']],
            [
                'email.required' => 'Please enter your email address.',
                'email.email' => 'Please enter a valid email address.',
                'email.max' => 'Please enter a valid email address.',
            ]
        );

        $throttleKey = 'forgot-password|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_REQUESTS_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('email', "Too many requests. Please try again in {$seconds} seconds.");

            return;
        }

        RateLimiter::hit($throttleKey);

        // The outcome is deliberately ignored: the screen looks the same whether or not the address has an
        // account, so this form cannot be used to discover who is registered.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')
            ->layout('layouts.app', ['title' => 'Reset your password - UniMarket']);
    }
}
