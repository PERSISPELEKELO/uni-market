<?php

declare(strict_types=1);

namespace App\Livewire\Ratings;

use App\Models\Appeal;
use App\Models\Rating;
use App\Services\AppealWorkflowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Lets any signed-in student flag someone else's review for admin attention.
 * Reports are stored as Appeal records (target_type = 'Rating') so they reuse
 * the existing Reports & Appeals admin queue and workflow rather than a new table.
 */
class ReportReview extends Component
{
    public Rating $rating;

    public bool $open = false;

    public bool $submitted = false;

    public string $reason = '';

    public string $details = '';

    private const REASON_LABELS = [
        'offensive' => 'Offensive content',
        'spam' => 'Spam',
        'false' => 'False or inappropriate content',
        'harassment' => 'Harassment',
        'other' => 'Other',
    ];

    public function mount(Rating $rating): void
    {
        $this->rating = $rating;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function submit(AppealWorkflowService $appeals): void
    {
        $user = Auth::user();

        if (! $user || ! Gate::allows('create', Appeal::class)) {
            $this->dispatch('notify', type: 'error', message: 'You cannot report this review.');

            return;
        }

        if ($user->id === $this->rating->rater_id) {
            $this->dispatch('notify', type: 'error', message: 'You cannot report your own review.');

            return;
        }

        $this->validate(
            ['reason' => ['required', 'in:offensive,spam,false,harassment,other'], 'details' => ['nullable', 'string', 'max:1000']],
            ['reason.required' => 'Please choose a reason before submitting.']
        );

        $alreadyReported = $this->rating->reports()
            ->where('user_id', $user->id)
            ->whereIn('status', [Appeal::STATUS_PENDING, Appeal::STATUS_UNDER_REVIEW])
            ->exists();

        if ($alreadyReported) {
            $this->dispatch('notify', type: 'error', message: 'You have already reported this review. A moderator is checking it.');

            return;
        }

        try {
            $appeals->submitAppeal(
                $user,
                'Rating',
                $this->rating->id,
                self::REASON_LABELS[$this->reason].(filled($this->details) ? ': '.trim($this->details) : '')
            );

            $this->submitted = true;
            $this->open = false;
            $this->dispatch('notify', type: 'success', message: 'Thank you - a moderator will review this.');
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('notify', type: 'error', message: $exception->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.ratings.report-review');
    }
}
