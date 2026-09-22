<?php

namespace App\Livewire\Account;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use WithFileUploads;

    private const MAX_PASSWORD_ATTEMPTS = 5;

    private const AVATAR_DISK = 'public';

    private const AVATAR_PATH_PREFIX = 'avatars';

    public string $name = '';

    public ?string $phone_number = null;

    public ?string $bio = null;

    public ?string $programme = null;

    public ?string $business_type = null;

    public $avatar = null;

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->phone_number = $user->phone_number;
        $this->bio = $user->bio;
        $this->programme = $user->programme;
        $this->business_type = $user->business_type;
    }

    public function updateProfile(): void
    {
        $this->name = trim($this->name);
        $this->phone_number = filled(trim((string) $this->phone_number)) ? trim((string) $this->phone_number) : null;
        $this->bio = filled(trim((string) $this->bio)) ? trim((string) $this->bio) : null;
        $this->programme = filled(trim((string) $this->programme)) ? trim((string) $this->programme) : null;
        $this->business_type = filled(trim((string) $this->business_type)) ? trim((string) $this->business_type) : null;

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()\-]{7,20}$/', Rule::unique('users', 'phone_number')->ignore(Auth::id())],
            // Deliberately no minimum length: a short bio like "New here!" is legitimate.
            'bio' => ['nullable', 'string', 'max:1000'],
            'programme' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:150'],
        ], [
            'name.required' => 'Please enter your full name.',
            'name.min' => 'Please enter your full name.',
            'phone_number.regex' => 'Please enter a valid phone number, for example +260971234567.',
            'phone_number.unique' => 'This phone number is already registered to another account.',
            'bio.max' => 'Your bio can be at most 1,000 characters long.',
            'programme.max' => 'Please keep this under 150 characters.',
            'business_type.max' => 'Please keep this under 150 characters.',
        ]);

        Auth::user()->update([
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'bio' => $this->bio,
            'programme' => $this->programme,
            'business_type' => $this->business_type,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Your details have been saved.');
    }

    public function updateAvatar(): void
    {
        $this->validate(
            ['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']],
            [
                'avatar.required' => 'Please choose a photo to upload.',
                'avatar.image' => 'Please upload an image file.',
                'avatar.mimes' => 'Please upload a JPG, PNG or WebP image.',
                'avatar.max' => 'The photo must be 2 MB or smaller.',
            ]
        );

        $user = Auth::user();
        $oldPath = $user->avatar_path;

        $newPath = $this->avatar->storeAs(
            self::AVATAR_PATH_PREFIX.'/'.$user->id,
            Str::uuid().'.'.$this->avatar->getClientOriginalExtension(),
            self::AVATAR_DISK
        );

        $user->update(['avatar_path' => $newPath]);

        if ($oldPath) {
            Storage::disk(self::AVATAR_DISK)->delete($oldPath);
        }

        $this->avatar = null;
        $this->dispatch('notify', type: 'success', message: 'Your profile photo has been updated.');
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk(self::AVATAR_DISK)->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        $this->dispatch('notify', type: 'success', message: 'Your profile photo has been removed.');
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
        return view('livewire.account.profile', ['user' => Auth::user()->fresh()])
            ->layout('layouts.app', ['title' => 'My Account - UniMarket']);
    }
}
