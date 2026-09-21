<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">My account</h1>
        <p class="mt-1 text-sm text-slate-600">Manage your details and keep your account secure.</p>
    </div>

    <section class="card space-y-4 p-5 sm:p-6" aria-labelledby="identity-heading">
        <h2 id="identity-heading" class="text-base font-semibold text-ink">Your identity</h2>
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-slate-600">Email address</dt>
                <dd class="mt-0.5 break-all font-semibold text-ink">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-600">Student ID</dt>
                <dd class="mt-0.5 font-semibold text-ink">{{ $user->student_id ?? 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-600">Verification</dt>
                <dd class="mt-0.5">
                    @if ($user->is_verified)
                        <span class="badge badge-success"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Official Student</span>
                    @elseif ($user->hasVerifiedEmail())
                        <span class="badge badge-info">Email verified - a university email is needed for the student badge</span>
                    @else
                        <span class="badge badge-warning">Email not verified</span>
                        <a href="{{ route('verification.notice') }}" class="ml-1 text-sm font-semibold text-brand-800 underline underline-offset-2">Verify now</a>
                    @endif
                </dd>
            </div>
        </dl>
        <p class="text-xs text-slate-600">Your email address and student ID cannot be changed here. Contact support if they need correcting.</p>
    </section>

    <form wire:submit="updateProfile" novalidate class="card space-y-5 p-5 sm:p-6" aria-labelledby="details-heading">
        <h2 id="details-heading" class="text-base font-semibold text-ink">Your details</h2>

        <div>
            <label for="name" class="form-label">Full name</label>
            <input id="name" type="text" wire:model="name" autocomplete="name" class="form-input" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror />
            <x-form-error name="name" />
        </div>

        <div>
            <label for="phone_number" class="form-label">Phone number <span class="font-normal text-slate-600">(optional)</span></label>
            <input id="phone_number" type="tel" wire:model="phone_number" autocomplete="tel" inputmode="tel" placeholder="+260971234567" class="form-input" @error('phone_number') aria-invalid="true" aria-describedby="phone_number-error" @enderror />
            <x-form-error name="phone_number" />
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updateProfile">
                <span wire:loading.remove wire:target="updateProfile">Save details</span>
                <span wire:loading wire:target="updateProfile">Saving...</span>
            </button>
        </div>
    </form>

    <form wire:submit="updatePassword" novalidate class="card space-y-5 p-5 sm:p-6" aria-labelledby="password-heading">
        <h2 id="password-heading" class="text-base font-semibold text-ink">Change password</h2>

        <div>
            <label for="current_password" class="form-label">Current password</label>
            <x-password-input id="current_password" wire:model="current_password" autocomplete="current-password" />
            <x-form-error name="current_password" />
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="new_password" class="form-label">New password</label>
                <x-password-input id="new_password" wire:model="new_password" autocomplete="new-password" />
                <p class="form-hint">At least 8 characters, with a letter and a number.</p>
                <x-form-error name="new_password" />
            </div>
            <div>
                <label for="new_password_confirmation" class="form-label">Confirm new password</label>
                <x-password-input id="new_password_confirmation" wire:model="new_password_confirmation" autocomplete="new-password" />
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updatePassword">
                <span wire:loading.remove wire:target="updatePassword">Change password</span>
                <span wire:loading wire:target="updatePassword">Changing...</span>
            </button>
        </div>
    </form>
</div>
