<div class="mx-auto w-full max-w-md py-4 sm:py-8">
    <div class="card space-y-6 p-6 sm:p-8">

        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">Create your account</h1>
            <p class="mt-1 text-sm text-slate-600">Join your campus marketplace. It only takes a minute.</p>
        </div>

        <form wire:submit="register" novalidate class="space-y-5">
            @error('form')
                <x-alert type="error">{{ $message }}</x-alert>
            @enderror

            <div>
                <label for="name" class="form-label">Full name</label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    placeholder="Chileshe Mwansa"
                    class="form-input"
                    @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                />
                <x-form-error name="name" />
            </div>

            <div>
                <label for="email" class="form-label">Email address</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="you@example.com"
                    class="form-input"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                />
                <x-form-error name="email" />
            </div>

            <div>
                <label for="student_id" class="form-label">Student ID number</label>
                <input
                    id="student_id"
                    type="text"
                    wire:model="student_id"
                    autocomplete="off"
                    placeholder="2024198273"
                    class="form-input"
                    @error('student_id') aria-invalid="true" aria-describedby="student_id-error" @enderror
                />
                <x-form-error name="student_id" />
            </div>

            <div>
                <label for="phone_number" class="form-label">Phone number <span class="font-normal text-slate-600">(optional)</span></label>
                <input
                    id="phone_number"
                    type="tel"
                    wire:model="phone_number"
                    autocomplete="tel"
                    inputmode="tel"
                    placeholder="+260971234567"
                    class="form-input"
                    @error('phone_number') aria-invalid="true" aria-describedby="phone_number-error" @enderror
                />
                <x-form-error name="phone_number" />
            </div>

            <div>
                <label for="password" class="form-label">Password</label>
                <x-password-input id="password" wire:model="password" autocomplete="new-password" placeholder="At least 8 characters" />
                <p class="form-hint">Use at least 8 characters, with a letter and a number.</p>
                <x-form-error name="password" />
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirm password</label>
                <x-password-input id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" placeholder="Type your password again" />
                <x-form-error name="password_confirmation" />
            </div>

            <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove wire:target="register">Create account</span>
                <span wire:loading wire:target="register">Creating account...</span>
            </button>
        </form>

        <p class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-brand-800 underline underline-offset-2 hover:text-brand-900">Log in</a>
        </p>
    </div>
</div>
