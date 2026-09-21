<?php

namespace App\Livewire\Account;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Profile extends Component
{
    private const MAX_PASSWORD_ATTEMPTS = 5;

    public string $name = '';

    public ?string $phone_number = null;

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->phone_number = $user->phone_number;
    }

    public function updateProfile(): void
    {
        $this->name = trim($this->name);
        $this->phone_number = filled(trim((string) $this->phone_number)) ? trim((string) $this->phone_number) : null;

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()\-]{7,20}$/', Rule::unique('users', 'phone_number')->ignore(Auth::id())],
        ], [
            'name.required' => 'Please enter your full name.',
            'name.min' => 'Please enter your full name.',
            'phone_number.regex' => 'Please enter a valid phone number, for example +260971234567.',
            'phone_number.unique' => 'This phone number is already registered to another account.',
        ]);

        Auth::user()->update(['name' => $this->name, 'phone_number' => $this->phone_number]);

        $this->dispatch('notify', type: 'success', message: 'Your details have been saved.');
    }

    public function updatePassword(): void
    {
        $this->resetErrorBag();

        $throttleKey = 'change-password|'.Auth::id();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PASSWORD_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('current_password', "Too many attempts. Please try again in {$seconds} seconds.");

            return;
        }

        RateLimiter::hit($throttleKey);

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'current_password.current_password' => 'That is not your current password.',
            'new_password.required' => 'Please choose a new password.',
            'new_password.min' => 'Your new password must be at least 8 characters long.',
            'new_password.letters' => 'Your new password must include at least one letter.',
            'new_password.numbers' => 'Your new password must include at least one number.',
            'new_password.confirmed' => 'The two new passwords do not match.',
            'new_password.different' => 'Your new password must be different from your current one.',
        ]);

        $user = Auth::user();
        $user->update(['password' => $this->new_password]);

        app(AuditLoggerService::class)->recordAction($user, 'PASSWORD_CHANGED', 'User', (string) $user->id, []);

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        $this->dispatch('notify', type: 'success', message: 'Your password has been changed.');
    }

    public function render()
    {
        return view('livewire.account.profile', ['user' => Auth::user()])
            ->layout('layouts.app', ['title' => 'My Account - UniMarket']);
    }
}
