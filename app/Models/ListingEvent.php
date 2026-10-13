<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unified, append-only activity log behind the seller/buyer insights
 * dashboards - see the create_listing_events_table migration docblock.
 */
class ListingEvent extends Model
{
    use HasFactory;

    public const TYPE_VIEW = 'view';

    public const TYPE_SAVE = 'save';

    public const TYPE_SHARE = 'share';

    public const TYPE_MESSAGE = 'message';

    public $timestamps = false;

    protected $fillable = [
        'listing_id',
        'user_id',
        'session_hash',
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

    public function scopeRecent(Builder $query, int $hours): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
