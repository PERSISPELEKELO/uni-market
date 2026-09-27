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
        'actor_id_snapshot',
        'actor_name_snapshot',
        'actor_role',
        'action',
        'target_type',
        'target_id',
        'payload',
        'previous_hash',
        'current_hash',
    ];

    /**
     * The actor's name at the time of this action, surviving deletion of
     * the actor's account. Prefers the live relationship (more up to date,
     * e.g. reflects a name change) for an actor who still exists, then the
     * immutable snapshot for one who has since been deleted, and only says
     * "System" when there was genuinely never an actor at all - as opposed
     * to a legacy pre-snapshot row whose actor was deleted before this
     * column existed, which has no way left to recover a name for.
     */
    public function actorDisplayName(): string
    {
        if ($this->actor !== null) {
            return $this->actor->name;
        }

        if ($this->actor_name_snapshot !== null) {
            return "{$this->actor_name_snapshot} (deleted)";
        }

        return $this->actor_id_snapshot === null && $this->actor_id === null
            ? 'System'
            : 'Deleted user';
    }

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
