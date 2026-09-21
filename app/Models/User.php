<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
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

    /**
     * Whether the user still has to confirm their email to earn the verified badge.
     * Members verified before email confirmation existed keep their badge.
     */
    public function isAwaitingEmailVerification(): bool
    {
        return ! $this->hasVerifiedEmail() && ! $this->is_verified;
    }

    /**
     * Whether the email domain is accepted for the "Official Student" badge.
     * With no STUDENT_EMAIL_DOMAINS configured, every domain is accepted.
     */
    public function hasStudentEmailDomain(): bool
    {
        $allowed = config('unimarket.student_email_domains', []);

        if ($allowed === []) {
            return true;
        }

        $domain = strtolower(substr(strrchr((string) $this->email, '@') ?: '', 1));

        return collect($allowed)->contains(fn (string $suffix): bool => $domain === $suffix || str_ends_with($domain, '.'.$suffix));
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
        ];
    }
}
