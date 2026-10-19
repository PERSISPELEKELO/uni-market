<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unified impression/click log for every recommendation carousel - see the
 * create_recommendation_events_table migration docblock.
 */
class RecommendationEvent extends Model
{
    public const TYPE_IMPRESSION = 'impression';

    public const TYPE_CLICK = 'click';

    public const SECTION_RECOMMENDED_FOR_YOU = 'recommended_for_you';

    public const SECTION_BECAUSE_YOU_VIEWED = 'because_you_viewed';

    public const SECTION_TRENDING = 'trending';

    public const SECTION_GOOD_DEALS = 'good_deals';

    public const SECTION_JUST_LISTED = 'just_listed';

    public const SECTION_SIMILAR_ITEMS = 'similar_items';

    public const SECTION_STUDENTS_ALSO_VIEWED = 'students_also_viewed';

    public $timestamps = false;

    protected $fillable = [
        'listing_id',
        'user_id',
        'session_hash',
        'section',
        'type',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }
}
