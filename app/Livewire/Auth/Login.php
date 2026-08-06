<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\AuditLog;

class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    protected array $rules = [
        'email' => 'required|email',
        'password' => 'required|string',
    ];

    public function login()
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();

            app(\App\Services\AuditLoggerService::class)->log(
                'USER_LOGIN',
                'User',
                Auth::id(),
                ['email' => $this->email],
                Auth::user()
            );

            return redirect()->intended(route('listings.index'));
        }

        $this->addError('email', 'The provided credentials do not match our campus user records.');
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('layouts.app', ['title' => 'Student Login - UniMarket']);
    }
}
