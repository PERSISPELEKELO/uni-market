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

    /**
     * Standard reasons an admin can pick when rejecting a document - kept as
     * short codes (rather than free text) so the audit log never has to
     * store whatever an admin happened to type.
     *
     * @var array<string, string>
     */
    public const REJECTION_REASONS = [
        'blurry_or_unclear' => 'The photo is too blurry or unclear to read.',
        'card_not_fully_visible' => 'The whole card is not visible in the photo.',
        'wrong_side' => 'This shows the back, not the front, of the card.',
        'name_mismatch' => "The name on the card doesn't match your registered name.",
        'number_mismatch' => "The student number on the card doesn't match your registered number.",
        'not_own_card' => "This doesn't appear to be your own student ID card.",
        'other' => 'Other',
    ];

    /**
     * @var array<string, string>
     */
    public const RESUBMISSION_REASONS = [
        'blurry_or_unclear' => 'Please upload a clearer photo - this one is too blurry to read.',
        'card_not_fully_visible' => 'Please make sure the whole card is visible in the photo.',
        'wrong_side' => 'Please upload a photo of the front of your card.',
        'glare_or_lighting' => 'Please retake the photo in better lighting and without glare.',
        'other' => 'Other',
    ];

    protected $fillable = [
        'user_id',
        'file_path',
        'file_deleted_at',
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
        'file_deleted_at' => 'datetime',
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

    public function isFileDeleted(): bool
    {
        return $this->file_deleted_at !== null;
    }
}
