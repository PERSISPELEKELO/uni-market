<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'student_id',
        'phone_number',
        'password',
        'role',
        'is_verified',
        'student_verification_status',
        'student_verification_submitted_at',
        'student_verification_reviewed_at',
        'student_verification_reviewed_by',
        'student_verification_rejection_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Transaction::class, 'buyer_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Transaction::class, 'seller_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'raised_by');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(Appeal::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(StudentVerificationDocument::class)->latest('id');
    }

    /**
     * Student identity verification states. Deliberately separate from
     * `email_verified_at` (proves only that the member owns the email address)
     * and mirrored onto `is_verified` (the public "Verified Student" badge),
     * which becomes true only via VERIFIED and false for every other state.
     */
    public const STUDENT_VERIFICATION_NOT_SUBMITTED = 'not_submitted';

    public const STUDENT_VERIFICATION_PENDING = 'pending';

    public const STUDENT_VERIFICATION_VERIFIED = 'verified';

    public const STUDENT_VERIFICATION_REJECTED = 'rejected';

    public const STUDENT_VERIFICATION_RESUBMISSION_REQUIRED = 'resubmission_required';

    /**
     * Whether the user still has to confirm their email before they can submit
     * a student document at all.
     */
    public function isAwaitingEmailVerification(): bool
    {
        return ! $this->hasVerifiedEmail();
    }

    public function isStudentVerified(): bool
    {
        return $this->student_verification_status === self::STUDENT_VERIFICATION_VERIFIED;
    }

    /**
     * A document may be submitted (or resubmitted) once the email is confirmed and
     * there isn't already one awaiting review or already approved.
     */
    public function canSubmitStudentVerification(): bool
    {
        return $this->hasVerifiedEmail() && in_array($this->student_verification_status, [
            self::STUDENT_VERIFICATION_NOT_SUBMITTED,
            self::STUDENT_VERIFICATION_REJECTED,
            self::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED,
        ], true);
    }

    /**
     * One combined label covering both verification dimensions, matching the
     * account-status list students and admins see throughout the platform.
     */
    public function verificationStatusLabel(): string
    {
        if (! $this->hasVerifiedEmail()) {
            return 'Email Unverified';
        }

        return match ($this->student_verification_status) {
            self::STUDENT_VERIFICATION_VERIFIED => 'Verified Student',
            self::STUDENT_VERIFICATION_PENDING => 'Email Verified - Student Verification Pending',
            self::STUDENT_VERIFICATION_REJECTED => 'Verification Rejected',
            self::STUDENT_VERIFICATION_RESUBMISSION_REQUIRED => 'Resubmission Required',
            default => 'Email Verified - Student Verification Pending',
        };
    }

    public function isStudent(): bool
    {
        return $this->role === 'student' || empty($this->role);
    }

    public function isGovernanceCommittee(): bool
    {
        return $this->role === 'governance_committee';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Only admin and governance-committee accounts may sign in to the admin panel.
     * Without this, Filament allows any authenticated user in by default.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->isGovernanceCommittee();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'student_verification_submitted_at' => 'datetime',
            'student_verification_reviewed_at' => 'datetime',
        ];
    }
}
