<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class VerifyEmail extends Component
{
    private const MAX_RESENDS_PER_MINUTE = 3;

    public bool $sent = false;

    public function resend(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $throttleKey = 'verify-email-resend|'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_RESENDS_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('resend', "Please wait {$seconds} seconds before asking for another email.");

            return;
        }

        RateLimiter::hit($throttleKey);

        $user->sendEmailVerificationNotification();

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.verify-email', ['user' => Auth::user()])
            ->layout('layouts.app', ['title' => 'Verify your email - UniMarket']);
    }
}
