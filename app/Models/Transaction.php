<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'buyer_id',
        'seller_id',
        'amount',
        'status',
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

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
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
}
