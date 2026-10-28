@php
    $tabs = [
        'needs_action' => 'Needs your action',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'disputed' => 'Disputed / Refunded',
    ];
    $stepLabels = ['Reserved', 'Meeting', 'Inspection', 'Completed'];
@endphp

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink">My Transactions</h1>
        <p class="mt-1 text-sm text-slate-600">Everything you're buying or selling that isn't finished yet.</p>
    </div>

    <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" role="tablist" aria-label="Transaction status">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                role="tab"
                wire:click="setTab('{{ $key }}')"
                @class(['chip flex-shrink-0', 'chip-active' => $activeTab === $key])
                aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
            >
                {{ $label }}
                @if ($counts[$key] > 0)
                    <span class="badge badge-neutral px-1.5 py-0 text-[10px]">{{ $counts[$key] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($grouped[$activeTab] as $row)
            @php $transaction = $row['transaction']; @endphp
            <div class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5" wire:key="transaction-{{ $transaction->id }}">
                <x-listing-image :src="$transaction->listing?->cover_thumbnail_url" :alt="$transaction->listing?->title ?? 'Listing'" class="h-20 w-20 flex-shrink-0 rounded-lg sm:h-16 sm:w-16" fit="cover" />

                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge {{ $row['role'] === 'buying' ? 'badge-info' : 'badge-neutral' }}">
                            {{ $row['role'] === 'buying' ? 'You are buying' : 'You are selling' }}
                        </span>
                        <span class="text-xs text-slate-500">
                            {{ $row['role'] === 'buying' ? 'Seller' : 'Buyer' }}: {{ $row['role'] === 'buying' ? $transaction->seller?->name : $transaction->buyer?->name }}
                        </span>
                    </div>

                    <p class="truncate font-semibold text-ink">{{ $transaction->listing?->title ?? 'Listing no longer available' }}</p>
                    <p class="text-sm text-slate-700">K{{ number_format((float) $transaction->amount, 2) }} &middot; {{ $row['status_label'] }}</p>

                    @if ($row['inspection_ends_at'] && $row['inspection_ends_at']->isFuture())
                        <p class="text-xs font-medium text-accent-700">{{ $row['inspection_ends_at']->diffForHumans(null, true) }} left to inspect</p>
                    @endif

                    <div class="flex items-center gap-1" aria-label="Progress: {{ $stepLabels[$row['step'] - 1] }}">
                        @foreach ($stepLabels as $index => $label)
                            <span @class(['h-1.5 w-6 rounded-full', 'bg-brand-700' => $index < $row['step'], 'bg-slate-200 dark:bg-slate-300' => $index >= $row['step']])></span>
                        @endforeach
                        <span class="ml-1.5 text-[11px] text-slate-500">{{ $stepLabels[$row['step'] - 1] }}</span>
                    </div>

                    <p class="text-xs text-slate-500">Started {{ $transaction->created_at->format('M j, Y') }}</p>
                </div>

                <a href="{{ route('transactions.tracker', $transaction) }}" class="btn btn-primary flex-shrink-0 sm:btn-sm">
                    {{ $row['action_label'] ?? 'View' }}
                </a>
            </div>
        @empty
            <div class="card p-8 text-center">
                <p class="text-sm text-slate-600">
                    @switch($activeTab)
                        @case('needs_action')
                            Nothing needs your attention right now.
                            @break
                        @case('in_progress')
                            No transactions are currently in progress.
                            @break
                        @case('completed')
                            No completed transactions yet.
                            @break
                        @default
                            No disputed or refunded transactions.
                    @endswitch
                </p>
            </div>
        @endforelse
    </div>
</div>
