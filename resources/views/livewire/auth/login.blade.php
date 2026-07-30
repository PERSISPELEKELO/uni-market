<div class="max-w-md mx-auto py-8">
    <div class="bg-white border border-slate-200 rounded-lg p-6 sm:p-8 shadow-sm space-y-6">

        <div class="text-center">
            <div class="w-12 h-12 rounded-lg bg-[#1E293B] text-white flex items-center justify-center font-semibold text-xl mx-auto shadow-sm">
                U
            </div>
            <h2 class="text-xl font-semibold text-[#0F172A] tracking-tight mt-3">Welcome Back</h2>
            <p class="text-xs text-slate-500 font-regular mt-1">
                Log in with your registered student account.
            </p>
        </div>

        <form wire:submit.prevent="login" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-1.5">Email Address</label>
                <input
                    type="email"
                    wire:model="email"
                    placeholder="email@example.com"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669]"
                />
                @error('email') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-1.5">Password</label>
                <input
                    type="password"
                    wire:model="password"
                    placeholder="••••••••"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669]"
                />
                @error('password') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center space-x-2 text-slate-600 cursor-pointer">
                    <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-[#059669] focus:ring-[#059669]" />
                    <span>Remember me</span>
                </label>
            </div>

            <button
                type="submit"
                class="w-full bg-[#1E293B] hover:bg-[#312E81] text-white font-medium py-3 px-4 rounded-lg text-sm transition shadow-sm"
            >
                Log In to Campus Market
            </button>
        </form>

        <div class="text-center pt-2 border-t border-slate-100 text-xs text-slate-500">
            Don't have an account? <a href="{{ route('register') }}" class="text-[#312E81] font-semibold hover:underline">Register here</a>
        </div>
    </div>
</div>
