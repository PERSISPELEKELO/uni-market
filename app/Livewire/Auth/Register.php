<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Register extends Component
{
    public string $name = '';
    public string $email = '';
    public string $student_id = '';
    public ?string $phone_number = null;
    public string $password = '';
    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'student_id' => 'required|string|unique:users,student_id',
            'phone_number' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function register()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => strtolower($this->email),
            'student_id' => $this->student_id,
            'phone_number' => !empty($this->phone_number) ? $this->phone_number : null,
            'password' => Hash::make($this->password),
            'role' => 'student',
            'is_verified' => true,
        ]);

        // Audit log
        app(\App\Services\AuditLoggerService::class)->log(
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

        return redirect()->route('listings.index')
            ->with('success', 'Account created successfully! Welcome to UniMarket.');
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('layouts.app', ['title' => 'Student Sign-Up - UniMarket']);
    }
}
