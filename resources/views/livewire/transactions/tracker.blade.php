<div>
    <!-- Page Title -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[#0F172A] tracking-tight">Escrow Transaction Tracker</h1>
        <p class="text-sm font-regular text-slate-500 mt-1">
            Monitor physical handoffs with OTP verification, active inspection countdowns, and dispute resolution.
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
                                {{ $tx->buyer_id === auth()->id() ? 'Buying from ' . ($tx->seller->name ?? 'Seller') : 'Selling to ' . ($tx->buyer->name ?? 'Buyer') }}
                            </div>
                        </div>

                        <!-- Status Badge -->
                        @php
                            $txStatus = strtoupper($tx->status);
                            $badgeStyle = match($txStatus) {
                                'COMPLETED' => 'bg-[#059669]/10 text-[#059669] border-[#059669]/20',
                                'DISPUTED' => 'bg-[#D97706]/10 text-[#D97706] border-[#D97706]/20',
                                'ITEM_INSPECTION', 'HANDED_OVER' => 'bg-amber-100 text-amber-800 border-amber-300',
                                'PENDING_MEETING', 'INITIATED', 'RESERVED' => 'bg-blue-50 text-blue-700 border-blue-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200'
                            };
                        @endphp
                        <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded border {{ $badgeStyle }}">
                            {{ str_replace('_', ' ', $txStatus) }}
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
                @php
                    $normalizedStatus = strtoupper($activeTransaction->status);
                    $step = match($normalizedStatus) {
                        'INITIATED', 'RESERVED' => 1,
                        'PENDING_MEETING', 'PENDING' => 2,
                        'ITEM_INSPECTION', 'HANDED_OVER' => 3,
                        'COMPLETED', 'DISPUTED' => 4,
                        default => 2
                    };
                    $isDisputed = $normalizedStatus === 'DISPUTED';
                    $isCompleted = $normalizedStatus === 'COMPLETED';
                    $isInspection = in_array($normalizedStatus, ['ITEM_INSPECTION', 'HANDED_OVER'], true);
                    $isPendingMeeting = in_array($normalizedStatus, ['PENDING_MEETING', 'INITIATED', 'RESERVED', 'PENDING'], true);
                    $isBuyer = auth()->id() === $activeTransaction->buyer_id;
                    $isSeller = auth()->id() === $activeTransaction->seller_id;
                    $inspectionExpiry = $activeTransaction->inspection_expires_at ?? $activeTransaction->inspection_ends_at;

                    if (!$activeTransaction->handover_otp_plain && !$activeTransaction->handover_code_plain && $isPendingMeeting) {
                        app(\App\Services\HandoverVerificationService::class)->generateHandoverCode($activeTransaction);
                        $activeTransaction->refresh();
                    }
                    $otpPlain = $activeTransaction->handover_otp_plain ?? $activeTransaction->handover_code_plain;
                @endphp

                <div class="bg-white border border-slate-200 rounded-lg p-6 sm:p-8 shadow-sm space-y-6">

                    <!-- Flash Notifications -->
                    @if(session()->has('success'))
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-lg p-3 font-medium">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session()->has('error'))
                        <div class="bg-red-50 border border-red-200 text-red-800 text-xs rounded-lg p-3 font-medium">
                            {{ session('error') }}
                        </div>
                    @endif

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

                                <!-- Step 3: Item Inspection -->
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shadow-xs {{ $step >= 3 ? 'bg-[#1E293B]' : 'bg-slate-300' }}">
                                        3
                                    </div>
                                    <span class="text-xs font-medium text-[#0F172A] mt-2">Item Inspection</span>
                                    <span class="text-[10px] text-slate-400 font-regular">Buyer Verification</span>
                                </div>

                                <!-- Step 4: Completed OR Disputed -->
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shadow-xs {{ $isCompleted ? 'bg-[#059669]' : ($isDisputed ? 'bg-[#D97706]' : 'bg-slate-300') }}">
                                        @if($isDisputed) ! @elseif($isCompleted) &#10003; @else 4 @endif
                                    </div>
                                    <span class="text-xs font-medium mt-2 {{ $isDisputed ? 'text-[#D97706]' : ($isCompleted ? 'text-[#059669]' : 'text-[#0F172A]') }}">
                                        {{ $isDisputed ? 'Disputed' : 'Completed' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-regular">
                                        {{ $isDisputed ? 'Under Governance Review' : 'Funds Released' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DYNAMIC VIEW A: STEP 1 & 2 (PENDING_MEETING / INITIATED / RESERVED) -->
                    @if($isPendingMeeting)
                        @if($isBuyer)
                            <!-- Campus Handoff Code Card (Buyer View) -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Campus Handoff Code</p>
                                    <p class="text-sm text-gray-600 mt-0.5">Give this code to the seller when you meet in person to receive your item:</p>
                                </div>
                                <div class="bg-white border-2 border-dashed border-blue-400 rounded-md px-4 py-2 text-2xl font-bold tracking-widest text-blue-900 font-mono">
                                    {{ $otpPlain }}
                                </div>
                            </div>
                        @elseif($isSeller)
                            <!-- Handover Code Verification Form (Seller View) -->
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                                <p class="text-sm font-medium text-gray-700 mb-2">Confirm Meeting & Physical Handoff</p>
                                <form action="{{ route('transactions.verify-handover', $activeTransaction->id) }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    <input
                                        type="text"
                                        name="otp"
                                        wire:model="handoverOtp"
                                        maxlength="6"
                                        placeholder="Enter Buyer's 6-Digit Code"
                                        class="form-input rounded-md border-gray-300 text-center text-lg tracking-widest font-mono uppercase py-2 px-3 focus:ring-2 focus:ring-emerald-500"
                                        required
                                    />
                                    <button
                                        type="submit"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2 rounded-md text-xs transition shadow-xs flex items-center justify-center"
                                    >
                                        Verify Handoff & Start Inspection
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endif

                    <!-- DYNAMIC VIEW B: STEP 3 (ITEM_INSPECTION / HANDED_OVER) -->
                    @if($isInspection)
                        <!-- Inspection Countdown Timer Component -->
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2 text-amber-800 text-sm font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Item Inspection Window Active</span>
                            </div>
                            <div class="text-xs font-semibold text-amber-700">
                                Expires in: <span id="countdown-timer">{{ $inspectionExpiry ? $inspectionExpiry->diffForHumans() : '48 hours' }}</span>
                            </div>
                        </div>
                    @endif

                    <!-- Dispute Details Notice if Disputed -->
                    @if($isDisputed && $activeTransaction->dispute)
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

                    <!-- Action Controls & Conditional Buttons -->
                    <div class="pt-6 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="text-xs text-slate-500 font-regular">
                            @if($isCompleted)
                                <span class="text-[#059669] font-medium flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Escrow fulfilled and completed.</span>
                                </span>
                            @elseif($isDisputed)
                                <span class="text-[#D97706] font-medium">Dispute is undergoing governance moderation review.</span>
                            @elseif($isPendingMeeting && $isBuyer)
                                <span class="text-blue-700 font-medium">Give your 6-digit Campus Handoff Code to the seller upon receiving your item.</span>
                            @elseif($isPendingMeeting && $isSeller)
                                <span class="text-gray-700 font-medium">Enter the buyer's 6-digit code above once you meet in person.</span>
                            @elseif($isInspection && $isSeller)
                                <span class="text-amber-800 font-medium italic">Waiting for buyer inspection period to conclude or buyer to confirm completion.</span>
                            @elseif($isInspection && $isBuyer)
                                <span>Verify item condition before confirming completion. You may also raise a dispute if defects are found.</span>
                            @endif
                        </div>

                        <!-- CONDITIONAL ACTION BUTTONS: STEP 3 ONLY FOR BUYER -->
                        @if($isInspection && $isBuyer)
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
                    Describe the issue clearly (e.g. item condition mismatch, missing accessories, or defect). Your report will be automatically analyzed by our Python AI NLP microservice.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">Dispute Reason *</label>
                    <textarea
                        wire:model="disputeReason"
                        rows="4"
                        placeholder="Detail the defect or issue experienced during item inspection..."
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
                        Submit for AI & Governance Review
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
