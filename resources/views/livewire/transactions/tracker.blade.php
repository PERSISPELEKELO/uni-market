<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">My transactions</h1>
        <p class="mt-1 text-sm text-slate-600">
            Follow each purchase and sale from reservation to handover, inspection and completion.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-8">

        <section class="lg:col-span-4" aria-labelledby="transactions-heading">
            <h2 id="transactions-heading" class="mb-3 text-sm font-semibold text-slate-800">Your transactions</h2>

            @if ($transactions->isEmpty())
                <div class="card p-6 text-center text-sm text-slate-600">
                    <p>You have no transactions yet.</p>
                    <a href="{{ route('listings.index') }}" class="btn btn-primary btn-sm mt-3">Browse the marketplace</a>
                </div>
            @else
                <ul class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 lg:flex-col lg:overflow-visible lg:pb-0">
                    @foreach ($transactions as $tx)
                        <li wire:key="tx-{{ $tx->id }}" class="w-72 flex-shrink-0 lg:w-auto">
                            <button
                                type="button"
                                wire:click="selectTransaction({{ $tx->id }})"
                                aria-current="{{ $selectedTransactionId === $tx->id ? 'true' : 'false' }}"
                                @class(['card w-full space-y-3 p-4 text-left transition-colors', 'border-brand-700 ring-2 ring-brand-200' => $selectedTransactionId === $tx->id, 'hover:border-slate-400' => $selectedTransactionId !== $tx->id])
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-ink">{{ $tx->listing->title ?? 'Campus item' }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-600">
                                            {{ $tx->buyer_id === auth()->id() ? 'Buying from '.($tx->seller->name ?? 'seller') : 'Selling to '.($tx->buyer->name ?? 'buyer') }}
                                        </p>
                                    </div>
                                    <x-status-badge :status="$tx->status" class="flex-shrink-0" />
                                </div>
                                <div class="flex items-center justify-between border-t border-slate-100 pt-2 text-xs">
                                    <span class="text-sm font-bold text-ink">K{{ number_format($tx->amount, 2) }}</span>
                                    <span class="text-slate-600">{{ $tx->created_at->format('M j, Y') }}</span>
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="lg:col-span-8" aria-live="polite">
            @if ($activeTransaction)
                @php
                    $normalizedStatus = strtoupper($activeTransaction->status);
                    $step = match ($normalizedStatus) {
                        'INITIATED', 'RESERVED' => 1,
                        'PENDING_MEETING', 'PENDING' => 2,
                        'ITEM_INSPECTION', 'HANDED_OVER' => 3,
                        'COMPLETED', 'DISPUTED' => 4,
                        default => 2,
                    };
                    $isDisputed = $normalizedStatus === 'DISPUTED';
                    $isCompleted = $normalizedStatus === 'COMPLETED';
                    $isInspection = $activeTransaction->isInInspection();
                    $isPendingMeeting = in_array($normalizedStatus, ['PENDING_MEETING', 'INITIATED', 'RESERVED', 'PENDING'], true);
                    $isBuyer = $activeTransaction->isBuyer(auth()->user());
                    $isSeller = $activeTransaction->isSeller(auth()->user());
                    $inspectionExpiry = $activeTransaction->inspection_expires_at ?? $activeTransaction->inspection_ends_at;
                    $otpPlain = $activeTransaction->handover_otp_plain ?? $activeTransaction->handover_code_plain;
                    $steps = [
                        1 => ['Reserved', 'Item held for you'],
                        2 => ['Meet-up', 'Campus handover'],
                        3 => ['Inspection', 'Buyer checks item'],
                        4 => [$isDisputed ? 'Disputed' : 'Completed', $isDisputed ? 'Under review' : 'Sale finished'],
                    ];
                @endphp

                <div class="card space-y-6 p-4 sm:p-6">

                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Transaction #{{ $activeTransaction->id }}</p>
                            <h2 class="mt-1 break-words text-xl font-bold text-ink">{{ $activeTransaction->listing->title ?? 'Listing item' }}</h2>
                            <p class="mt-0.5 text-sm text-slate-600">Started {{ $activeTransaction->created_at->format('F j, Y \a\t g:i A') }}</p>
                        </div>
                        <div class="sm:text-right">
                            <p class="text-xs text-slate-600">Amount</p>
                            <p class="text-2xl font-bold text-ink">K{{ number_format($activeTransaction->amount, 2) }}</p>
                        </div>
                    </div>

                    <ol class="grid grid-cols-4 gap-1 text-center sm:gap-2" aria-label="Transaction progress">
                        @foreach ($steps as $number => [$label, $hint])
                            @php
                                $reached = $step >= $number;
                                $isLast = $number === 4;
                                $circle = $isLast && $isDisputed ? 'bg-warn-600 text-white' : ($isLast && $isCompleted ? 'bg-accent-700 text-white' : ($reached ? 'bg-brand-700 text-white' : 'bg-slate-200 text-slate-700'));
                            @endphp
                            <li class="flex flex-col items-center" @if ($step === $number) aria-current="step" @endif>
                                <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold {{ $circle }}">
                                    @if ($isLast && $isDisputed) ! @elseif ($isLast && $isCompleted || ($reached && $number < $step)) &#10003; @else {{ $number }} @endif
                                </span>
                                <span class="mt-2 break-words text-xs font-semibold text-ink">{{ $label }}</span>
                                <span class="hidden text-[11px] text-slate-600 sm:block">{{ $hint }}</span>
                            </li>
                        @endforeach
                    </ol>

                    @if ($isPendingMeeting && $isBuyer)
                        <div class="rounded-xl border border-info-200 bg-info-50 p-4">
                            <p class="text-sm font-semibold text-info-800">Your handover code</p>
                            <p class="mt-0.5 text-sm text-gray-700">Give this code to the seller only when you meet in person and receive the item.</p>
                            <p class="mt-3 select-all rounded-lg border-2 border-dashed border-info-700 bg-white px-4 py-3 text-center font-mono text-3xl font-bold tracking-[0.3em] text-ink dark:bg-slate-800" aria-label="Handover code {{ $otpPlain }}">{{ $otpPlain }}</p>
                        </div>
                    @elseif ($isPendingMeeting && $isSeller)
                        <form wire:submit="verifyHandoverOtp" novalidate class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <label for="handoverOtp" class="form-label">Confirm the handover</label>
                            <p class="mb-3 text-sm text-slate-600">Meet the buyer, hand over the item, then enter the 6-digit code they show you.</p>
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <input
                                    id="handoverOtp"
                                    type="text"
                                    wire:model="handoverOtp"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    maxlength="6"
                                    placeholder="000000"
                                    class="form-input text-center font-mono text-xl tracking-[0.3em] sm:max-w-[12rem]"
                                    @error('handoverOtp') aria-invalid="true" aria-describedby="handoverOtp-error" @enderror
                                />
                                <button type="submit" class="btn btn-success" wire:loading.attr="disabled" wire:target="verifyHandoverOtp">
                                    Verify and start inspection
                                </button>
                            </div>
                            <x-form-error name="handoverOtp" />
                        </form>
                    @endif

                    @if ($isInspection)
                        <div class="flex flex-col gap-1 rounded-xl border border-warn-200 bg-warn-50 p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <span class="flex items-center gap-2 font-semibold text-warn-800"><x-app-icon name="clock" class="h-5 w-5" /> Inspection window is open</span>
                            @if ($inspectionExpiry)
                                <span class="text-warn-800">Closes <time datetime="{{ $inspectionExpiry->toIso8601String() }}">{{ $inspectionExpiry->diffForHumans() }}</time></span>
                            @endif
                        </div>
                    @endif

                    @if ($isDisputed && $activeTransaction->dispute)
                        @php $dispute = $activeTransaction->dispute; @endphp
                        <div class="space-y-3 rounded-xl border border-warn-200 bg-warn-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-warn-800">Dispute report</span>
                                <span class="badge badge-warning">{{ ucfirst($dispute->status) }}</span>
                            </div>
                            <p class="break-words text-sm text-gray-800"><strong>Reason:</strong> {{ $dispute->reason }}</p>

                            <div class="rounded-lg border border-warn-200 bg-white p-3 text-sm dark:bg-slate-800">
                                <p class="font-semibold text-ink">AI dispute analysis</p>
                                @if (! is_null($dispute->ai_sentiment_score))
                                    <p class="mt-1 text-slate-700">
                                        Sentiment score: <strong>{{ number_format($dispute->ai_sentiment_score, 2) }}</strong>
                                        @if (! is_null($dispute->ai_confidence_score))
                                            | Confidence: <strong>{{ number_format($dispute->ai_confidence_score * 100) }}%</strong>
                                        @endif
                                    </p>
                                    @if ($dispute->ai_analysis_summary)
                                        <p class="mt-1 italic text-slate-700">{{ $dispute->ai_analysis_summary }}</p>
                                    @endif
                                @else
                                    <p class="mt-1 text-slate-600">Analysis is not available yet. A moderator will review this dispute.</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col gap-4 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-700">
                            @if ($isCompleted)
                                <span class="inline-flex items-center gap-1.5 font-semibold text-accent-800 dark:text-accent-300"><x-app-icon name="check-circle" class="h-5 w-5" /> This transaction is complete.</span>
                            @elseif ($isDisputed)
                                <span class="font-semibold text-warn-800 dark:text-warn-300">This dispute is waiting for moderator review.</span>
                            @elseif ($isPendingMeeting && $isBuyer)
                                Arrange a time and place with the seller in <a href="{{ route('chat.thread', ['receiver' => $activeTransaction->seller_id, 'listing' => $activeTransaction->listing_id]) }}" class="font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2">Messages</a>.
                            @elseif ($isPendingMeeting && $isSeller)
                                Arrange a meet-up in <a href="{{ route('chat.thread', ['receiver' => $activeTransaction->buyer_id, 'listing' => $activeTransaction->listing_id]) }}" class="font-semibold text-brand-800 dark:text-brand-300 underline underline-offset-2">Messages</a>, then enter the buyer's code above.
                            @elseif ($isInspection && $isSeller)
                                Waiting for the buyer to finish inspecting the item.
                            @elseif ($isInspection && $isBuyer)
                                Check the item. Confirm if it is as described, or raise a dispute if it is not.
                            @endif
                        </p>

                        @if ($isInspection && $isBuyer)
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-shrink-0">
                                <button type="button" wire:click="openDisputeModal" class="btn btn-warning">Raise a dispute</button>
                                <button type="button" wire:click="markCompleted" wire:loading.attr="disabled" wire:target="markCompleted" class="btn btn-success">
                                    <x-app-icon name="check-circle" class="h-5 w-5" /> Confirm completion
                                </button>
                            </div>
                        @endif
                    </div>

                    @if ($isCompleted)
                        @livewire('ratings.rate-transaction', ['transaction' => $activeTransaction], key('rate-'.$activeTransaction->id))
                    @endif
                </div>
            @else
                <div class="card p-10 text-center text-sm text-slate-600">
                    Select a transaction to see its progress.
                </div>
            @endif
        </section>
    </div>

    @if ($showDisputeModal)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
            x-data
            x-on:keydown.escape.window="$wire.closeDisputeModal()"
        >
            <div class="max-h-[92vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6 dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="dispute-title">
                <div class="flex items-start justify-between gap-4">
                    <h3 id="dispute-title" class="text-lg font-bold text-ink">Raise a dispute</h3>
                    <button type="button" wire:click="closeDisputeModal" class="-m-2 rounded-lg p-2 text-slate-600 hover:bg-slate-100">
                        <x-app-icon name="x" class="h-5 w-5" />
                        <span class="sr-only">Close</span>
                    </button>
                </div>

                <p class="text-sm text-slate-600">
                    Describe what is wrong, for example the item does not match the listing, has a defect or is missing accessories.
                    A moderator will review your report.
                </p>

                <form wire:submit="submitDispute" novalidate class="space-y-4">
                    <div>
                        <label for="disputeReason" class="form-label">What went wrong?</label>
                        <textarea
                            id="disputeReason"
                            wire:model="disputeReason"
                            rows="4"
                            maxlength="2000"
                            autofocus
                            placeholder="Describe the issue you found while inspecting the item..."
                            class="form-input"
                            @error('disputeReason') aria-invalid="true" aria-describedby="disputeReason-error" @enderror
                        ></textarea>
                        <x-form-error name="disputeReason" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeDisputeModal" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-warning" wire:loading.attr="disabled" wire:target="submitDispute">Submit dispute</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
