<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rating;
use App\Models\User;
use App\Notifications\ReviewModerated;
use DomainException;

/**
 * Admin moderation of individual reviews, kept deliberately separate from
 * RatingService: the reputation *score* is always calculated from real
 * ratings (see User::averageRating), never set by an admin. Hiding a review
 * only removes it from that calculation and from public display - it never
 * touches the underlying stars value.
 */
class RatingModerationService
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    public function hide(Rating $rating, User $admin, ?string $reason = null): Rating
    {
        if ($rating->isHidden()) {
            throw new DomainException('This review is already hidden.');
        }

        $rating->update(['status' => Rating::STATUS_HIDDEN]);

        $this->auditLogger->recordAction(
            $admin,
            'RATING_HIDDEN',
            'Rating',
            (string) $rating->id,
            ['reason' => $reason]
        );

        $rating->rater->notify(new ReviewModerated($rating, 'hidden'));

        return $rating;
    }

    public function restore(Rating $rating, User $admin): Rating
    {
        if (! $rating->isHidden()) {
            throw new DomainException('This review is not hidden.');
        }

        $rating->update(['status' => Rating::STATUS_VISIBLE]);

        $this->auditLogger->recordAction(
            $admin,
            'RATING_RESTORED',
            'Rating',
            (string) $rating->id,
            []
        );

        $rating->rater->notify(new ReviewModerated($rating, 'restored'));

        return $rating;
    }
}
