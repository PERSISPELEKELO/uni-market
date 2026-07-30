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

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'USER_LOGIN',
                'entity_type' => 'App\Models\User',
                'entity_id' => Auth::id(),
                'payload' => ['email' => $this->email],
                'ip_address' => request()->ip(),
            ]);

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
