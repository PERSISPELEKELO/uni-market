<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppealStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appeal extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_UPHELD = 'UPHELD';
    public const STATUS_OVERTURNED = 'OVERTURNED';

    protected $fillable = [
        'user_id',
        'target_type',
        'target_id',
        'reason',
        'evidence_urls',
        'status',
        'governance_notes',
        'resolved_at',
    ];

    protected $casts = [
        'status' => AppealStatus::class,
        'evidence_urls' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === AppealStatus::PENDING || $this->status === self::STATUS_PENDING;
    }

    public function isUnderReview(): bool
    {
        return $this->status === AppealStatus::UNDER_REVIEW || $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isUpheld(): bool
    {
        return $this->status === AppealStatus::UPHELD || $this->status === self::STATUS_UPHELD;
    }

    public function isOverturned(): bool
    {
        return $this->status === AppealStatus::OVERTURNED || $this->status === self::STATUS_OVERTURNED;
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [AppealStatus::UPHELD, AppealStatus::OVERTURNED, self::STATUS_UPHELD, self::STATUS_OVERTURNED], true);
    }
}
