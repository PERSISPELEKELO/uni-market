<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt by a student to prove their identity to an administrator.
 * The underlying file lives on the private disk and is only ever served
 * through DocumentController, which checks StudentVerificationDocumentPolicy.
 */
class StudentVerificationDocument extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Files are stored under storage/app/private/{PATH_PREFIX}/{user_id}/...
     * The "local" disk root is storage/app/private, which is never web-accessible.
     */
    public const DISK = 'local';

    public const PATH_PREFIX = 'verification-documents';

    protected $fillable = [
        'user_id',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'status',
        'admin_notes',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
