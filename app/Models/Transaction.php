<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'category_id',
        'buyer_id',
        'seller_id',
        'amount',
        'status',
        'transaction_mode',
        'handover_code_hash',
        'handover_code_plain',
        'handover_otp_hash',
        'handover_otp_plain',
        'handover_code_expires_at',
        'handed_over_at',
        'handover_attempts',
        'inspection_period_hours',
        'inspection_duration_hours',
        'inspection_ends_at',
        'inspection_expires_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'handover_attempts' => 'integer',
        'inspection_period_hours' => 'integer',
        'inspection_duration_hours' => 'integer',
        'handover_code_expires_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'inspection_ends_at' => 'datetime',
        'inspection_expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Transactions the given user takes part in, either as buyer or as seller.
     */
    public function scopeForParticipant(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('buyer_id', $userId)->orWhere('seller_id', $userId);
        });
    }

    public function isBuyer(?User $user): bool
    {
        return $user !== null && $user->id === $this->buyer_id;
    }

    public function isSeller(?User $user): bool
    {
        return $user !== null && $user->id === $this->seller_id;
    }

    public function isInInspection(): bool
    {
        return in_array(strtoupper((string) $this->status), ['ITEM_INSPECTION', 'HANDED_OVER'], true);
    }

    /**
     * A snapshot taken at creation time from the listing's category - never
     * a live lookup, so changing a category's mode later cannot change how
     * an in-progress or already-completed transaction behaves.
     */
    public function isDirectMode(): bool
    {
        return strtoupper((string) $this->transaction_mode) === Category::MODE_DIRECT;
    }

    public function isInspectionMode(): bool
    {
        return ! $this->isDirectMode();
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * The listing's category *at the time of this transaction* - see the
     * migration that added this column. Always prefer this over
     * listing->category for any BI/reporting query, so a later
     * recategorisation of the listing can't rewrite history.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
