<div class="mx-auto grid w-full max-w-5xl grid-cols-1 gap-8 py-4 sm:py-8 lg:grid-cols-2 lg:gap-12">
    <div class="hidden lg:block">
        <a href="{{ route('listings.index') }}" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-brand-900 dark:text-brand-300">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</span>
            UniMarket
        </a>
        <h1 class="mt-6 text-2xl font-bold tracking-tight text-ink">Join the community</h1>
        <p class="mt-2 text-slate-600">Create your account to start buying and selling on campus - it only takes a minute.</p>

        <ul class="mt-6 space-y-3 text-sm text-slate-700">
            <li class="flex items-center gap-2"><x-app-icon name="check-circle" class="h-4 w-4 flex-shrink-0 text-accent-700" /> Verified student badges you can trust</li>
            <li class="flex items-center gap-2"><x-app-icon name="check-circle" class="h-4 w-4 flex-shrink-0 text-accent-700" /> In-app chat and protected handovers</li>
            <li class="flex items-center gap-2"><x-app-icon name="check-circle" class="h-4 w-4 flex-shrink-0 text-accent-700" /> Build a reputation that follows you after graduation</li>
        </ul>
    </div>

    <x-card padding="p-6 sm:p-8" class="space-y-6">
        <div class="text-center lg:hidden">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-xl font-bold text-white shadow-sm" aria-hidden="true">U</div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">Create your account</h1>
            <p class="mt-1 text-sm text-slate-600">Join your campus marketplace. It only takes a minute.</p>
        </div>

        <form wire:submit="register" novalidate class="space-y-5">
            @error('form')
                <x-alert type="error">{{ $message }}</x-alert>
            @enderror

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-input label="First name" name="first_name" icon="user" wire:model="first_name" autocomplete="given-name" placeholder="Chileshe" />
                <x-input label="Last name" name="last_name" icon="user" wire:model="last_name" autocomplete="family-name" placeholder="Mwansa" />
            </div>

            <x-input
                label="Email address"
                name="email"
                type="email"
                icon="mail"
                wire:model="email"
                autocomplete="email"
                inputmode="email"
                autocapitalize="none"
                spellcheck="false"
                oninput="this.value = this.value.toLowerCase()"
                placeholder="you@example.com"
            />

            <x-input
                label="Student ID number"
                name="student_id"
                icon="graduation-cap"
                wire:model="student_id"
                autocomplete="off"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="10"
                placeholder="2024198273"
                hint="Numbers only, up to 10 digits."
            />

            <x-input
                label="Phone number"
                name="phone_number"
                icon="phone"
                type="tel"
                optional
                wire:model="phone_number"
                autocomplete="tel"
                inputmode="tel"
                placeholder="+260971234567"
            />

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="intake_year" class="form-label">Year you started</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <x-app-icon name="graduation-cap" class="h-5 w-5" />
                        </span>
                        <select
                            id="intake_year"
                            wire:model="intake_year"
                            class="form-input pl-11"
                            @error('intake_year') aria-invalid="true" aria-describedby="intake_year-error" @enderror
                        >
                            <option value="">Choose a year</option>
                            @foreach ($intakeYears as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="form-hint">We work out your current year of study from this.</p>
                    <x-form-error name="intake_year" />
                </div>

                <div>
                    <label for="school" class="form-label">School</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <x-app-icon name="building" class="h-5 w-5" />
                        </span>
                        <select
                            id="school"
                            wire:model="school"
                            class="form-input pl-11"
                            @error('school') aria-invalid="true" aria-describedby="school-error" @enderror
                        >
                            <option value="">Choose your school</option>
                            @foreach ($schools as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-form-error name="school" />
                </div>
            </div>

            <div>
                <label for="gender" class="form-label">Gender <span class="font-normal text-slate-600">(optional)</span></label>
                <select id="gender" wire:model="gender" class="form-input">
                    <option value="undisclosed">Prefer not to say</option>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                </select>
                <x-form-error name="gender" />
            </div>

            <p class="form-hint -mt-2">
                Your year, school and gender are used only to show anonymous trends, such as what first-year students buy - never shown with your name. Gender is optional.
            </p>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="form-label">Password</label>
                    <x-password-input id="password" wire:model="password" autocomplete="new-password" placeholder="At least 8 characters" icon="lock" />
                    <p class="form-hint">At least 8 characters, a letter and a number.</p>
                    <x-form-error name="password" />
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Confirm password</label>
                    <x-password-input id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" placeholder="Type it again" icon="lock" />
                    <x-form-error name="password_confirmation" />
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove wire:target="register">Create account</span>
                <span wire:loading wire:target="register">Creating account...</span>
            </button>
        </form>

        <p class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2 hover:text-brand-900 dark:hover:text-brand-200">Log in</a>
        </p>
    </x-card>
</div>
