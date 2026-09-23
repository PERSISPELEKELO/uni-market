<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = Str::lower((string) request()->query('email', ''));
    }

    public function resetPassword()
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'email' => ['required', 'string', 'email:rfc,strict,filter', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please choose a new password.',
            'password.min' => 'Your password must be at least 8 characters long.',
            'password.letters' => 'Your password must include at least one letter.',
            'password.numbers' => 'Your password must include at least one number.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $status = PasswordBroker::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                app(AuditLoggerService::class)->recordAction($user, 'PASSWORD_RESET', 'User', (string) $user->id, []);

                event(new PasswordReset($user));
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            $this->password = '';
            $this->password_confirmation = '';
            $this->addError('email', 'This password reset link is invalid or has expired. Please request a new one.');

            return null;
        }

        return redirect()->route('login')
            ->with('success', 'Your password has been reset. Log in with your new password.');
    }

    public function render()
    {
        return view('livewire.auth.reset-password')
            ->layout('layouts.app', ['title' => 'Choose a new password - UniMarket']);
    }
}
