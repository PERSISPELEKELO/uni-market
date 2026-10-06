<?php

declare(strict_types=1);

namespace App\Livewire\Appeals;

use App\Enums\AppealStatus;
use App\Models\Appeal;
use App\Models\Listing;
use App\Models\User;
use App\Services\AppealWorkflowService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * The first real frontend for Appeals - previously a fully working backend
 * (AppealWorkflowService, AppealPolicy, the admin "Reports & Appeals" page)
 * with no way for a student to actually use it except a raw API call. Calls
 * the existing service directly, the same way ReportReview already does for
 * reporting a review - no new appeal-handling logic here.
 *
 * Scope: appealing a rejected student verification, or a suspended listing
 * you own. Appealing an account *suspension* is deliberately out of scope -
 * a suspended account is signed out immediately (EnsureUserIsNotSuspended)
 * and cannot reach an authenticated page at all, so that needs its own,
 * separate, unauthenticated flow this change does not attempt to build.
 */
class MyAppeals extends Component
{
    public string $reason = '';

    public function submitListingAppeal(int $listingId, AppealWorkflowService $appeals): void
    {
        $listing = Listing::where('user_id', Auth::id())->find($listingId);

        if (! $listing || $listing->status !== Listing::STATUS_SUSPENDED) {
            $this->dispatch('notify', type: 'error', message: 'This listing is not currently suspended.');

            return;
        }

        $this->submit($appeals, 'Listing', $listing->id);
    }

    public function submitVerificationAppeal(AppealWorkflowService $appeals): void
    {
        $user = Auth::user();

        if ($user->student_verification_status !== User::STUDENT_VERIFICATION_REJECTED) {
            $this->dispatch('notify', type: 'error', message: 'Your student verification is not currently rejected.');

            return;
        }

        $this->submit($appeals, 'User', $user->id);
    }

    private function submit(AppealWorkflowService $appeals, string $targetType, int $targetId): void
    {
        $alreadyPending = Appeal::where('user_id', Auth::id())
            ->where('target_type', $targetType)
            ->where('target_id', (string) $targetId)
            ->whereIn('status', [AppealStatus::PENDING, AppealStatus::UNDER_REVIEW])
            ->exists();

        if ($alreadyPending) {
            $this->dispatch('notify', type: 'error', message: 'You already have an appeal for this under review.');

            return;
        }

        $this->validate(
            ['reason' => ['required', 'string', 'min:10', 'max:2000']],
            [
                'reason.required' => 'Please explain why you are appealing this decision.',
                'reason.min' => 'Please give a little more detail - at least 10 characters.',
            ]
        );

        try {
            $appeals->submitAppeal(Auth::user(), $targetType, $targetId, $this->reason);
            $this->reason = '';
            $this->dispatch('notify', type: 'success', message: 'Your appeal has been submitted for review.');
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());
        }
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.appeals.my-appeals', [
            'appeals' => $user->appeals()->latest()->get(),
            'suspendedListings' => $user->listings()->where('status', Listing::STATUS_SUSPENDED)->get(),
            'verificationRejected' => $user->student_verification_status === User::STUDENT_VERIFICATION_REJECTED,
        ])->layout('layouts.app', ['title' => 'My Appeals - UniMarket']);
    }
}
