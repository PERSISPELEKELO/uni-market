<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Dispute;
use App\Models\Appeal;
use RuntimeException;

class ReputationExporterService
{
    /**
     * Export normalized and asymmetrically signed user data and reputation statistics.
     */
    public function exportUserData(User $user, ?string $privateKeyPem = null, ?string $publicKeyPem = null): array
    {
        if (!$privateKeyPem || !$publicKeyPem) {
            [$privateKeyPem, $publicKeyPem] = $this->generateKeyPair();
        }

        $payloadData = $this->compileNormalizedData($user);

        $jsonPayload = json_encode(AuditLoggerService::sortKeys($payloadData), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $binarySignature = '';
        $success = openssl_sign($jsonPayload, $binarySignature, $privateKeyPem, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new RuntimeException('Failed to generate RSA asymmetric signature for user data export.');
        }

        return [
            'version' => '1.0',
            'issuer' => 'University Marketplace Platform',
            'data' => $payloadData,
            'signature' => base64_encode($binarySignature),
            'algorithm' => 'SHA256withRSA',
            'public_key' => $publicKeyPem,
        ];
    }

    /**
     * Legacy alias method exportReputation.
     */
    public function exportReputation(User $user, ?string $privateKeyPem = null, ?string $publicKeyPem = null): array
    {
        return $this->exportUserData($user, $privateKeyPem, $publicKeyPem);
    }

    /**
     * Verify an asymmetrically signed user data payload.
     */
    public function verifyExportSignature(array $exportPackage): bool
    {
        if (
            empty($exportPackage['data']) ||
            empty($exportPackage['signature']) ||
            empty($exportPackage['public_key'])
        ) {
            return false;
        }

        $jsonPayload = json_encode(AuditLoggerService::sortKeys($exportPackage['data']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $binarySignature = base64_decode($exportPackage['signature']);
        $publicKeyPem = $exportPackage['public_key'];

        $result = openssl_verify($jsonPayload, $binarySignature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /**
     * Compile normalized payload data structure.
     */
    public function compileNormalizedData(User $user): array
    {
        $purchasesCount = Transaction::where('buyer_id', $user->id)->where('status', 'completed')->count();
        $salesCount = Transaction::where('seller_id', $user->id)->where('status', 'completed')->count();
        $totalCompleted = $purchasesCount + $salesCount;

        $disputesRaised = Dispute::where('raised_by', $user->id)->count();

        // Calculate positive vs negative review counts based on transaction history and disputes
        $positiveReviews = max(0, $totalCompleted - $disputesRaised);
        $negativeReviews = $disputesRaised;

        return [
            'user_id' => $user->id,
            'account_registration_date' => $user->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'total_completed_transactions' => $totalCompleted,
            'positive_reviews_count' => $positiveReviews,
            'negative_reviews_count' => $negativeReviews,
            'institutional_verification_state' => $user->is_verified ? 'VERIFIED' : 'UNVERIFIED',
            'reputation_score' => max(0, min(100, 50 + ($user->is_verified ? 25 : 0) + min($totalCompleted * 5, 25) - ($negativeReviews * 10))),
        ];
    }

    /**
     * Generate an RSA keypair for asymmetric signing.
     */
    public function generateKeyPair(): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = @openssl_pkey_new($config);

        if (!$res) {
            $cnfPath = sys_get_temp_dir() . '/openssl_unimarket.cnf';
            if (!file_exists($cnfPath)) {
                file_put_contents($cnfPath, "[ req ]\ndefault_bits = 2048\n[ req_distinguished_name ]\n");
            }
            $config['config'] = $cnfPath;
            $res = openssl_pkey_new($config);
        }

        if (!$res) {
            throw new RuntimeException('Unable to create new RSA private key: ' . openssl_error_string());
        }

        openssl_pkey_export($res, $privateKeyPem, null, $config);
        $keyDetails = openssl_pkey_get_details($res);
        $publicKeyPem = $keyDetails['key'];

        return [$privateKeyPem, $publicKeyPem];
    }
}
