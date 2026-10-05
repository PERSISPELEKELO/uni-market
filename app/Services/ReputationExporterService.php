<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lets a student export their own reputation and completed-transaction
 * history as a JSON file that stays verifiable after graduation, once their
 * account may no longer exist in a form anyone can query.
 *
 * Every reputation figure here is read from the same source of truth as the
 * profile page (User::averageRating() etc. - see RatingService) - this
 * service never computes its own reputation number.
 *
 * Signature scheme: RSA PKCS#1 v1.5 with SHA-256 ("RSASSA-PKCS1-v1_5-SHA256"),
 * via PHP's built-in openssl_sign()/openssl_verify(). RSA-PSS was considered
 * but PHP's openssl extension has no PSS padding support in these functions,
 * and adding a third-party crypto library (e.g. phpseclib) for PSS alone was
 * judged not worth a new dependency for this project - PKCS#1 v1.5 is still
 * a real, secure, widely used asymmetric signature scheme.
 *
 * Key storage: a single RSA-2048 key pair, generated once and persisted on
 * the "local" disk (storage/app/private/keys - never web-served, matching
 * how StudentVerificationDocument already stores sensitive files). The
 * private key never leaves the server: it is used only inside this class to
 * produce a signature, and is never included in any response. Verification
 * always uses the server's own stored public key - never a key taken from
 * the file being verified - otherwise a tampered file could simply ship its
 * own matching key pair and "verify" itself.
 */
class ReputationExporterService
{
    private const KEY_DISK = 'local';

    private const PRIVATE_KEY_PATH = 'keys/reputation_signing_private.pem';

    private const PUBLIC_KEY_PATH = 'keys/reputation_signing_public.pem';

    public const SIGNATURE_ALGORITHM = 'RSASSA-PKCS1-v1_5-SHA256';

    public const EXPORT_VERSION = '1.0';

    private const ISSUER = 'University Marketplace Platform';

    /**
     * Build and sign the authenticated student's own reputation export.
     */
    public function exportUserData(User $user): array
    {
        $document = [
            'export_version' => self::EXPORT_VERSION,
            'issuer' => self::ISSUER,
            'credential_id' => (string) Str::uuid(),
            'issued_at' => now()->toIso8601String(),
            'student' => [
                // Safe, non-sensitive reference - not the student_id, email or phone number.
                'student_reference' => 'UM-'.$user->id,
                'name' => $user->name,
            ],
            'reputation' => [
                'average_rating' => $user->averageRating(),
                'total_ratings' => $user->ratingsCount(),
                'completed_transactions' => $user->completedTransactionsCount(),
                'rating_distribution' => $user->ratingBreakdown(),
            ],
            'ratings' => $this->exportableRatings($user),
            'transaction_history' => $this->exportableTransactions($user),
            'integrity' => [
                'hash_algorithm' => 'SHA-256',
                'signature_algorithm' => self::SIGNATURE_ALGORITHM,
                'key_id' => $this->keyId(),
                'signature' => null,
            ],
        ];

        $document['integrity']['signature'] = base64_encode($this->sign($document));

        return $document;
    }

    /**
     * @return array{valid: bool, reason: string}
     */
    public function verifyExport(array $document): array
    {
        $signatureB64 = $document['integrity']['signature'] ?? null;
        $keyId = $document['integrity']['key_id'] ?? null;

        if (! is_string($signatureB64) || ! is_string($keyId) || $signatureB64 === '') {
            return ['valid' => false, 'reason' => 'This file is not a recognized reputation export - it is missing required fields.'];
        }

        if (! hash_equals($this->keyId(), $keyId)) {
            return ['valid' => false, 'reason' => 'This file was not signed by a currently trusted UniMarket key.'];
        }

        $signature = base64_decode($signatureB64, true);

        if ($signature === false) {
            return ['valid' => false, 'reason' => 'This file is not a recognized reputation export - its signature is malformed.'];
        }

        $result = openssl_verify($this->canonicalize($document), $signature, $this->publicKey(), OPENSSL_ALGO_SHA256);

        return $result === 1
            ? ['valid' => true, 'reason' => 'Signature verified successfully. This record has not been modified.']
            : ['valid' => false, 'reason' => 'Signature verification failed - this file has been modified or is not authentic.'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRatings(User $user): array
    {
        return $user->ratingsReceived()
            ->visible()
            ->with('rater:id,name')
            ->latest()
            ->get()
            ->map(fn ($rating) => [
                'stars' => $rating->stars,
                'review' => $rating->comment,
                'from' => $rating->rater->name,
                'date' => $rating->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableTransactions(User $user): array
    {
        return Transaction::forParticipant($user->id)
            ->where('status', 'COMPLETED')
            ->with(['listing', 'buyer', 'seller'])
            ->latest('completed_at')
            ->get()
            ->map(function (Transaction $transaction) use ($user): array {
                $role = $transaction->isBuyer($user) ? 'buyer' : 'seller';
                $counterpart = $role === 'buyer' ? $transaction->seller : $transaction->buyer;

                return [
                    'transaction_id' => $transaction->id,
                    'listing_title' => $transaction->listing->title ?? 'Listing removed',
                    'role' => $role,
                    'counterpart_name' => $counterpart->name ?? 'Unknown',
                    'amount' => (float) $transaction->amount,
                    'completed_at' => $transaction->completed_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The exact bytes that are signed and, on verification, re-derived and
     * checked against the signature - the whole document with the signature
     * slot itself blanked out (it cannot sign its own value), sorted into a
     * canonical key order so formatting differences never affect the result.
     */
    private function canonicalize(array $document): string
    {
        $document['integrity']['signature'] = null;

        return json_encode(AuditLoggerService::sortKeys($document), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sign(array $document): string
    {
        $success = openssl_sign($this->canonicalize($document), $binarySignature, $this->privateKey(), OPENSSL_ALGO_SHA256);

        if (! $success) {
            throw new RuntimeException('Failed to sign the reputation export: '.openssl_error_string());
        }

        return $binarySignature;
    }

    /**
     * A short, stable fingerprint of the current public key, stored inside
     * every export so a verifier can tell at a glance whether it was signed
     * by the key this server currently trusts.
     */
    private function keyId(): string
    {
        return substr(hash('sha256', $this->publicKey()), 0, 16);
    }

    private function privateKey(): string
    {
        $this->ensureKeyPairExists();

        return Storage::disk(self::KEY_DISK)->get(self::PRIVATE_KEY_PATH);
    }

    private function publicKey(): string
    {
        $this->ensureKeyPairExists();

        return Storage::disk(self::KEY_DISK)->get(self::PUBLIC_KEY_PATH);
    }

    /**
     * Generates the signing key pair exactly once and persists both halves
     * to the private (never web-served) disk. Every export/verify call after
     * the first reuses these same files - a fresh key pair per call would
     * make files signed at different times impossible to verify consistently.
     */
    private function ensureKeyPairExists(): void
    {
        $disk = Storage::disk(self::KEY_DISK);

        if ($disk->exists(self::PRIVATE_KEY_PATH) && $disk->exists(self::PUBLIC_KEY_PATH)) {
            return;
        }

        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = @openssl_pkey_new($config);

        if (! $resource) {
            // On some Windows PHP/OpenSSL builds, openssl_pkey_new() cannot find its
            // default openssl.cnf and fails outright unless one is pointed to explicitly.
            $cnfPath = sys_get_temp_dir().'/openssl_unimarket.cnf';

            if (! file_exists($cnfPath)) {
                file_put_contents($cnfPath, "[ req ]\ndefault_bits = 2048\n[ req_distinguished_name ]\n");
            }

            $config['config'] = $cnfPath;
            $resource = openssl_pkey_new($config);
        }

        if (! $resource) {
            throw new RuntimeException('Unable to generate the reputation signing key pair: '.openssl_error_string());
        }

        openssl_pkey_export($resource, $privateKeyPem, null, $config);
        $publicKeyPem = openssl_pkey_get_details($resource)['key'];

        $disk->put(self::PRIVATE_KEY_PATH, $privateKeyPem);
        $disk->put(self::PUBLIC_KEY_PATH, $publicKeyPem);
    }
}
