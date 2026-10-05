<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    public const MIN_STARS = 1;

    public const MAX_STARS = 5;

    public const STATUS_VISIBLE = 'visible';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'transaction_id',
        'rater_id',
        'rated_id',
        'stars',
        'comment',
        'status',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function rated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VISIBLE);
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }

    /**
     * Reports filed against this review. Reports reuse the existing generic
     * Appeal model (target_type/target_id) rather than a new table - see
     * AppealResource, which already surfaces exactly this kind of report queue.
     */
    public function reports(): Builder
    {
        return Appeal::query()->where('target_type', 'Rating')->where('target_id', (string) $this->id);
    }
}
