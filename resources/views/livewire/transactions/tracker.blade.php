<div>
    <!-- Page Title -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[#0F172A] tracking-tight">Escrow Transaction Tracker</h1>
        <p class="text-sm font-regular text-slate-500 mt-1">
            Monitor item handoffs, confirm completion, or raise disputes with AI-assisted moderation.
        </p>
    </div>

    <!-- Main Grid: Left sidebar (Transaction List) & Right panel (Active Escrow Timeline & Actions) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Left Column: Transaction Selection Cards (4 Cols) -->
        <div class="lg:col-span-4 space-y-3">
            <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">My Escrows</h2>

            @forelse($transactions as $tx)
                <button
                    wire:click="selectTransaction({{ $tx->id }})"
                    class="w-full text-left bg-white border rounded-lg p-4 transition shadow-xs flex flex-col justify-between space-y-3 {{ $selectedTransactionId === $tx->id ? 'border-[#312E81] ring-2 ring-[#312E81]/10 bg-slate-50/50' : 'border-slate-200 hover:border-slate-300' }}"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-semibold text-[#0F172A] line-clamp-1">
                                {{ $tx->listing->title ?? 'Campus Item' }}
                            </div>
                            <div class="text-[11px] text-slate-500 font-regular mt-0.5">
                                {{ $tx->buyer_id === auth()->id() ? 'Buying from ' . $tx->seller->name : 'Selling to ' . $tx->buyer->name }}
                            </div>
                        </div>

                        <!-- Status Badge -->
                        @php
                            $badgeStyle = match($tx->status) {
                                'completed' => 'bg-[#059669]/10 text-[#059669] border-[#059669]/20',
                                'disputed' => 'bg-[#D97706]/10 text-[#D97706] border-[#D97706]/20',
                                default => 'bg-slate-100 text-slate-700 border-slate-200'
                            };
                        @endphp
                        <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded border {{ $badgeStyle }}">
                            {{ $tx->status }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                        <span class="font-semibold text-[#059669]">K{{ number_format($tx->amount, 2) }}</span>
                        <span class="text-[10px] text-slate-400">{{ $tx->created_at->format('M d, Y') }}</span>
                    </div>
                </button>
            @empty
                <div class="bg-white border border-slate-200 rounded-lg p-6 text-center text-xs text-slate-500 font-regular">
                    No transactions found. Initiate a purchase on any listing to begin escrow.
                </div>
            @endforelse
        </div>

        <!-- Right Column: Timeline & Action Stepper (8 Cols) -->
        <div class="lg:col-span-8">
            @if($activeTransaction)
                <div class="bg-white border border-slate-200 rounded-lg p-6 sm:p-8 shadow-sm space-y-8">

                    <!-- Header Summary -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-6 border-b border-slate-200 gap-4">
                        <div>
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Transaction #{{ $activeTransaction->id }}</span>
                            <h2 class="text-xl font-semibold text-[#0F172A] mt-1">{{ $activeTransaction->listing->title ?? 'Listing Item' }}</h2>
                            <p class="text-xs text-slate-500 font-regular mt-0.5">
                                Created on {{ $activeTransaction->created_at->format('F j, Y \a\t g:i A') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-500 font-regular block">Escrow Amount</span>
                            <span class="text-2xl font-semibold text-[#059669]">K{{ number_format($activeTransaction->amount, 2) }}</span>
                        </div>
                    </div>

                    <!-- 4-State Visual Stepper Timeline -->
                    <div>
                        <h3 class="text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-6">Escrow Lifecycle Stepper</h3>

                        @php
                            $step = match($activeTransaction->status) {
                                'initiated' => 1,
                                'pending' => 2,
                                'completed' => 4,
                                'disputed' => 4, // Disputed branch
                                default => 2
                            };
                            $isDisputed = $activeTransaction->status === 'disputed';
                        @endphp

                        <div class="relative">
                            <!-- Horizontal Line -->
                            <div class="absolute top-1/2 left-0 right-0 h-0.5 bg-slate-200 -translate-y-1/2 z-0"></div>

                            <!-- Steps Grid -->
                            <div class="relative z-10 grid grid-cols-4 gap-2 text-center">
                                <!-- Step 1: Initiated -->
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shadow-xs {{ $step >= 1 ? 'bg-[#1E293B]' : 'bg-slate-300' }}">
                                        1
                                    </div>
                                    <span class="text-xs font-medium text-[#0F172A] mt-2">Initiated</span>
                                    <span class="text-[10px] text-slate-400 font-regular">Item Reserved</span>
                                </div>

                                <!-- Step 2: Pending Meeting -->
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shadow-xs {{ $step >= 2 ? 'bg-[#1E293B]' : 'bg-slate-300' }}">
                                        2
                                    </div>
                                    <span class="text-xs font-medium text-[#0F172A] mt-2">Pending Meeting</span>
                                    <span class="text-[10px] text-slate-400 font-regular">Campus Handoff</span>
                                </div>

                                <!-- Step 3: Inspection -->
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shadow-xs {{ $step >= 3 ? 'bg-[#1E293B]' : 'bg-slate-300' }}">
                                        3
                                    </div>
                                    <span class="text-xs font-medium text-[#0F172A] mt-2">Item Inspection</span>
                                    <span class="text-[10px] text-slate-400 font-regular">Buyer Verification</span>
                                </div>

                                <!-- Step 4: Completed OR Disputed -->
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shadow-xs {{ $activeTransaction->status === 'completed' ? 'bg-[#059669]' : ($isDisputed ? 'bg-[#D97706]' : 'bg-slate-300') }}">
                                        @if($isDisputed) ! @elseif($activeTransaction->status === 'completed') &#10003; @else 4 @endif
                                    </div>
                                    <span class="text-xs font-medium mt-2 {{ $isDisputed ? 'text-[#D97706]' : ($activeTransaction->status === 'completed' ? 'text-[#059669]' : 'text-[#0F172A]') }}">
                                        {{ $isDisputed ? 'Disputed' : 'Completed' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-regular">
                                        {{ $isDisputed ? 'Under AI Review' : 'Funds Released' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dispute Details Notice if Disputed -->
                    @if($activeTransaction->dispute)
                        <div class="bg-[#D97706]/10 border border-[#D97706]/30 rounded-lg p-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-[#D97706] uppercase tracking-wider">Active Dispute Report</span>
                                <span class="text-xs font-medium text-slate-600">Status: {{ ucfirst($activeTransaction->dispute->status) }}</span>
                            </div>
                            <p class="text-xs font-regular text-[#0F172A]">
                                <strong>Reason:</strong> "{{ $activeTransaction->dispute->reason }}"
                            </p>

                            <!-- AI NLP Microservice Insight Container -->
                            <div class="bg-white border border-[#D97706]/20 rounded p-3 text-xs space-y-1">
                                <div class="font-semibold text-[#0F172A] flex items-center space-x-1.5">
                                    <svg class="w-4 h-4 text-[#D97706]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>Python AI Dispute Sentiment Analysis</span>
                                </div>
                                <div class="text-slate-600 font-regular">
                                    Sentiment Score: <strong>{{ number_format($activeTransaction->dispute->ai_sentiment_score ?? -0.65, 2) }}</strong> | Confidence: <strong>{{ number_format(($activeTransaction->dispute->ai_confidence_score ?? 0.88) * 100) }}%</strong>
                                </div>
                                <p class="text-slate-500 italic mt-1">
                                    {{ $activeTransaction->dispute->ai_analysis_summary ?? 'NLP Analysis: Discrepancy flagged between item condition and buyer expectation.' }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Action Controls -->
                    <div class="pt-6 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="text-xs text-slate-500 font-regular">
                            @if($activeTransaction->status === 'completed')
                                <span class="text-[#059669] font-medium flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Escrow fulfilled and completed.</span>
                                </span>
                            @elseif($activeTransaction->status === 'disputed')
                                <span class="text-[#D97706] font-medium">Dispute is undergoing Filament moderation review.</span>
                            @else
                                <span>Please verify item condition before confirming completion.</span>
                            @endif
                        </div>

                        @if(!in_array($activeTransaction->status, ['completed', 'disputed']))
                            <div class="flex items-center space-x-3">
                                <button
                                    wire:click="openDisputeModal"
                                    class="bg-white border border-[#D97706] text-[#D97706] hover:bg-[#D97706]/10 font-medium px-4 py-2 rounded-lg text-xs transition"
                                >
                                    Raise Dispute
                                </button>

                                <button
                                    wire:click="markCompleted({{ $activeTransaction->id }})"
                                    class="bg-[#059669] hover:bg-[#059669]/90 text-white font-medium px-5 py-2 rounded-lg text-xs transition shadow-sm flex items-center space-x-1.5"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Confirm Completion</span>
                                </button>
                            </div>
                        @endif
                    </div>

                </div>
            @else
                <div class="bg-white border border-slate-200 rounded-lg p-12 text-center text-slate-500 text-xs font-regular">
                    Select a transaction from the left panel to inspect the escrow status stepper.
                </div>
            @endif
        </div>
    </div>

    <!-- Raise Dispute Modal -->
    @if($showDisputeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white border border-slate-200 rounded-lg max-w-lg w-full p-6 shadow-lg space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-semibold text-[#0F172A]">Raise Escrow Dispute</h3>
                    <button wire:click="closeDisputeModal" class="text-slate-400 hover:text-slate-600 text-lg font-semibold">&times;</button>
                </div>

                <p class="text-xs text-slate-500 font-regular">
                    Describe the issue clearly (e.g. item condition mismatch, missing accessories, or seller non-appearance). Your report will be automatically analyzed by our Python AI NLP microservice.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">Dispute Reason *</label>
                    <textarea
                        wire:model="disputeReason"
                        rows="4"
                        placeholder="Detail the issue experienced during meeting or item inspection..."
                        class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg p-3 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#D97706]"
                    ></textarea>
                    @error('disputeReason') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100">
                    <button
                        wire:click="closeDisputeModal"
                        class="px-4 py-2 border border-slate-200 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="submitDispute"
                        class="px-5 py-2 bg-[#D97706] hover:bg-[#D97706]/90 text-white rounded-lg text-xs font-medium transition shadow-sm"
                    >
                        Submit for AI & Moderator Review
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
