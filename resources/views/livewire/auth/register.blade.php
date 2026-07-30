<div class="max-w-md mx-auto py-6">
    <!-- Centered Auth Card -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 sm:p-8 shadow-sm space-y-6">

        <div class="text-center">
            <div class="w-12 h-12 rounded-lg bg-[#1E293B] text-white flex items-center justify-center font-semibold text-xl mx-auto shadow-sm">
                U
            </div>
            <h2 class="text-xl font-semibold text-[#0F172A] tracking-tight mt-3">Student Registration</h2>
            <p class="text-xs text-slate-500 font-regular mt-1">
                Enter your student details to register your campus account.
            </p>
        </div>

        <form wire:submit.prevent="register" class="space-y-4">

            <!-- Full Name -->
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-1.5">Full Name *</label>
                <input
                    type="text"
                    wire:model="name"
                    placeholder="Chileshe Mwansa"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669]"
                />
                @error('name') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Email -->
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-1.5">Email Address *</label>
                <input
                    type="email"
                    wire:model="email"
                    placeholder="email@example.com"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669]"
                />
                @error('email') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Student ID -->
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-1.5">Student ID Number *</label>
                <input
                    type="text"
                    wire:model="student_id"
                    placeholder="2024198273"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669]"
                />
                @error('student_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Password -->
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-1.5">Password *</label>
                <input
                    type="password"
                    wire:model="password"
                    placeholder="••••••••"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669]"
                />
                @error('password') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Confirm Password -->
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-1.5">Confirm Password *</label>
                <input
                    type="password"
                    wire:model="password_confirmation"
                    placeholder="••••••••"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669]"
                />
            </div>

            <button
                type="submit"
                class="w-full bg-[#1E293B] hover:bg-[#312E81] text-white font-medium py-3 px-4 rounded-lg text-sm transition shadow-sm mt-2"
            >
                Create Verified Student Account
            </button>
        </form>

        <div class="text-center pt-2 border-t border-slate-100 text-xs text-slate-500">
            Already registered? <a href="{{ route('login') }}" class="text-[#312E81] font-semibold hover:underline">Log in here</a>
        </div>
    </div>
</div>
