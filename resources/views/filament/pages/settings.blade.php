<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Admin Profile</x-slot>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Name</dt>
                <dd class="mt-0.5 font-semibold">{{ auth()->user()->name }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Email</dt>
                <dd class="mt-0.5 font-semibold">{{ auth()->user()->email }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Role</dt>
                <dd class="mt-0.5 font-semibold">{{ auth()->user()->isAdmin() ? 'Admin' : 'Governance Committee' }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Security</x-slot>

        <form wire:submit="updatePassword" class="space-y-6">
            {{ $this->passwordForm }}

            <x-filament::button type="submit">
                Update password
            </x-filament::button>
        </form>
    </x-filament::section>

    @php($policy = $this->getMarketplacePolicy())

    <x-filament::section>
        <x-slot name="heading">Marketplace Policy</x-slot>
        <x-slot name="description">Read-only. These are set in the application configuration, not here.</x-slot>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">AI dispute analysis</dt>
                <dd class="mt-0.5">
                    <x-filament::badge :color="$policy['ai_enabled'] ? 'success' : 'gray'">
                        {{ $policy['ai_enabled'] ? 'Enabled' : 'Disabled' }}
                    </x-filament::badge>
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Inspection window</dt>
                <dd class="mt-0.5 font-semibold">{{ $policy['inspection_hours'] }} hours after handover</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
