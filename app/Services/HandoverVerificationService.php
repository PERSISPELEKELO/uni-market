<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class HandoverVerificationService
{
    public function __construct(
        protected AuditLoggerService $auditLogger
    ) {}

    /**
     * Generate a secure 6-digit numeric OTP code for physical handover verification.
     */
    public function generateHandoverCode(Transaction $transaction): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hashed = Hash::make($code);

        $transaction->update([
            'status' => in_array(strtoupper($transaction->status), ['INITIATED', 'RESERVED', 'PENDING'], true) ? 'PENDING_MEETING' : $transaction->status,
            'handover_code_hash' => $hashed,
            'handover_code_plain' => $code,
            'handover_otp_hash' => $hashed,
            'handover_otp_plain' => $code,
            'handover_code_expires_at' => now()->addDays(3),
            'handover_attempts' => 0,
        ]);

        return $code;
    }

    /**
     * Verify the 6-digit handover code entered by the seller during physical meet-up.
     */
    public function verifyHandoverCode(Transaction $transaction, string $code, User $seller): bool
    {
        if ($seller->id !== $transaction->seller_id) {
            throw new InvalidArgumentException('Only the seller can verify the handover code.');
        }

        if ($transaction->handover_attempts >= 5) {
            throw new DomainException('Maximum handover verification attempts exceeded.');
        }

        if ($transaction->handover_code_expires_at && now()->greaterThan($transaction->handover_code_expires_at)) {
            throw new DomainException('Handover code has expired.');
        }

        $submittedOtp = trim((string) $code);

        $isValid = ($transaction->handover_otp_plain && $transaction->handover_otp_plain === $submittedOtp)
            || ($transaction->handover_code_plain && $transaction->handover_code_plain === $submittedOtp)
            || ($transaction->handover_otp_hash && Hash::check($submittedOtp, $transaction->handover_otp_hash))
            || ($transaction->handover_code_hash && Hash::check($submittedOtp, $transaction->handover_code_hash));

        if (!$isValid) {
            $transaction->increment('handover_attempts');
            $remaining = 5 - $transaction->handover_attempts;
            throw new InvalidArgumentException("Invalid handover code. {$remaining} attempts remaining.");
        }

        $inspectionHours = $transaction->inspection_duration_hours ?? $transaction->inspection_period_hours ?? 48;
        $handedOverAt = now();
        $inspectionEndsAt = (clone $handedOverAt)->addHours($inspectionHours);

        $transaction->update([
            'status' => 'ITEM_INSPECTION',
            'handed_over_at' => $handedOverAt,
            'inspection_ends_at' => $inspectionEndsAt,
            'inspection_expires_at' => $inspectionEndsAt,
            'handover_attempts' => 0,
        ]);

        $this->auditLogger->recordAction(
            $seller,
            'TRANSACTION_HANDOVER_CONFIRMED',
            'Transaction',
            (string) $transaction->id,
            [
                'inspection_hours' => $inspectionHours,
                'handed_over_at' => $handedOverAt->toIso8601String(),
                'inspection_ends_at' => $inspectionEndsAt->toIso8601String(),
            ]
        );

        return true;
    }
}
