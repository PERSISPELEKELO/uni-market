<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">My account</h1>
            <p class="mt-1 text-sm text-slate-600">Manage your details and keep your account secure.</p>
        </div>
        <a href="{{ route('profiles.show', $user) }}" class="btn btn-secondary btn-sm">View my public profile</a>
    </div>

    <section class="card space-y-4 p-5 sm:p-6" aria-labelledby="photo-heading">
        <h2 id="photo-heading" class="text-base font-semibold text-ink">Profile photo</h2>

        <div class="flex flex-wrap items-center gap-4">
            <x-avatar :user="$user" class="h-16 w-16 text-xl" />

            <div class="flex-1">
                <form wire:submit="updateAvatar" novalidate class="flex flex-wrap items-center gap-3">
                    <label for="avatar" class="btn btn-secondary btn-sm cursor-pointer">
                        <x-app-icon name="upload" class="h-4 w-4" /> Choose photo
                        <input id="avatar" type="file" wire:model="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" />
                    </label>

                    @if ($avatar)
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="updateAvatar,avatar">
                            <span wire:loading.remove wire:target="updateAvatar">Save photo</span>
                            <span wire:loading wire:target="updateAvatar">Uploading...</span>
                        </button>
                    @endif

                    @if ($user->avatar_url)
                        <button type="button" wire:click="removeAvatar" wire:confirm="Remove your profile photo?" class="btn btn-danger-outline btn-sm">Remove</button>
                    @endif
                </form>

                <div wire:loading wire:target="avatar" class="mt-2 flex items-center gap-2 text-sm font-medium text-brand-800 dark:text-brand-300" role="status">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Uploading...
                </div>
                <p class="form-hint">JPG, PNG or WebP, up to 2 MB. Shown to other students in listings, messages and your profile.</p>
                <x-form-error name="avatar" />
            </div>
        </div>
    </section>

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
                <dt class="font-medium text-slate-600">Email status</dt>
                <dd class="mt-0.5">
                    @if ($user->hasVerifiedEmail())
                        <span class="badge badge-success"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Verified</span>
                    @else
                        <span class="badge badge-warning">Not verified</span>
                        <a href="{{ route('verification.notice') }}" class="ml-1 text-sm font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2">Verify now</a>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="font-medium text-slate-600">Student verification</dt>
                <dd class="mt-0.5">
                    @if ($user->is_verified)
                        <span class="badge badge-success"><x-app-icon name="check-circle" class="h-3.5 w-3.5" /> Verified Student</span>
                    @else
                        <span class="badge badge-warning">{{ $user->verificationStatusLabel() }}</span>
                        @if ($user->canSubmitStudentVerification())
                            <a href="{{ route('verification.student.form') }}" class="ml-1 text-sm font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2">Submit document</a>
                        @elseif ($user->student_verification_status === \App\Models\User::STUDENT_VERIFICATION_PENDING)
                            <a href="{{ route('verification.student.form') }}" class="ml-1 text-sm font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2">View status</a>
                        @endif
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

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="programme" class="form-label">Programme / course <span class="font-normal text-slate-600">(optional)</span></label>
                <input id="programme" type="text" wire:model="programme" placeholder="e.g. BSc Computer Science" class="form-input" @error('programme') aria-invalid="true" aria-describedby="programme-error" @enderror />
                <x-form-error name="programme" />
            </div>
            <div>
                <label for="business_type" class="form-label">Business type <span class="font-normal text-slate-600">(optional)</span></label>
                <input id="business_type" type="text" wire:model="business_type" placeholder="e.g. Reseller, Baking, Tailoring" class="form-input" @error('business_type') aria-invalid="true" aria-describedby="business_type-error" @enderror />
                <x-form-error name="business_type" />
            </div>
        </div>

        <div>
            <label for="bio" class="form-label">Bio <span class="font-normal text-slate-600">(optional)</span></label>
            <textarea id="bio" wire:model="bio" rows="3" maxlength="1000" placeholder="Tell other students a bit about yourself." class="form-input" @error('bio') aria-invalid="true" aria-describedby="bio-error" @enderror></textarea>
            <x-form-error name="bio" />
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
