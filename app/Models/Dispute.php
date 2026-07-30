<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'raised_by',
        'reason',
        'status',
        'ai_sentiment_score',
        'ai_confidence_score',
        'ai_suggested_resolution',
        'ai_analysis_summary',
    ];

    protected $casts = [
        'ai_sentiment_score' => 'float',
        'ai_confidence_score' => 'float',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}
