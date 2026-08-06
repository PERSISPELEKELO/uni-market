<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'timestamp',
        'actor_id',
        'actor_role',
        'action',
        'target_type',
        'target_id',
        'payload',
        'previous_hash',
        'current_hash',
    ];

    protected $casts = [
        'payload' => 'array',
        'timestamp' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
