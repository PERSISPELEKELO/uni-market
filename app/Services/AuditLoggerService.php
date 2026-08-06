<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLoggerService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Record an audit log action using pessimistic locking within a DB transaction.
     */
    public function recordAction(
        ?User $actor,
        string $action,
        string $targetType,
        string|int $targetId,
        array $payload
    ): AuditLog {
        return DB::transaction(function () use ($actor, $action, $targetType, $targetId, $payload) {
            $uuid = (string) Str::uuid();
            $timestamp = Carbon::now();
            $timestampStr = $timestamp->toIso8601String();

            $actorId = $actor ? (string) $actor->id : 'SYSTEM';
            $actorRole = $actor ? ($actor->role ?? 'student') : 'system';
            $targetIdStr = (string) $targetId;

            /** @var AuditLog|null $latest */
            $latest = AuditLog::lockForUpdate()->latest('id')->first();
            $previousHash = $latest ? $latest->current_hash : self::GENESIS_HASH;

            $currentHash = self::calculateHash(
                $previousHash,
                $timestampStr,
                $actorId,
                $action,
                $targetType,
                $targetIdStr,
                $payload
            );

            return AuditLog::create([
                'uuid' => $uuid,
                'timestamp' => $timestamp,
                'actor_id' => $actor?->id,
                'actor_role' => $actorRole,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetIdStr,
                'payload' => $payload,
                'previous_hash' => $previousHash,
                'current_hash' => $currentHash,
            ]);
        });
    }

    /**
     * Alias for recordAction to maintain backwards compatibility.
     */
    public function log(
        string $action,
        string $targetType,
        string|int $targetId,
        array $payload = [],
        ?User $actor = null,
        ?string $actorRole = null
    ): AuditLog {
        return $this->recordAction($actor, $action, $targetType, (string) $targetId, $payload);
    }

    /**
     * Verify the entire cryptographic hash chain integrity.
     * Returns ['is_valid' => bool, 'failed_at_id' => int|null]
     */
    public function verifyIntegrity(): array
    {
        $logs = AuditLog::orderBy('id', 'asc')->get();

        if ($logs->isEmpty()) {
            return [
                'is_valid' => true,
                'failed_at_id' => null,
            ];
        }

        $previousHash = self::GENESIS_HASH;

        foreach ($logs as $log) {
            if ($log->previous_hash !== $previousHash) {
                return [
                    'is_valid' => false,
                    'failed_at_id' => $log->id,
                ];
            }

            $timestampStr = $log->timestamp instanceof Carbon
                ? $log->timestamp->toIso8601String()
                : (string) $log->timestamp;

            $actorIdStr = $log->actor_id ? (string) $log->actor_id : 'SYSTEM';

            $payloadArray = is_array($log->payload)
                ? $log->payload
                : (json_decode((string) $log->payload, true) ?? []);

            $recomputedHash = self::calculateHash(
                $log->previous_hash,
                $timestampStr,
                $actorIdStr,
                $log->action,
                $log->target_type,
                (string) $log->target_id,
                $payloadArray
            );

            if ($log->current_hash !== $recomputedHash) {
                return [
                    'is_valid' => false,
                    'failed_at_id' => $log->id,
                ];
            }

            $previousHash = $log->current_hash;
        }

        return [
            'is_valid' => true,
            'failed_at_id' => null,
        ];
    }

    /**
     * Legacy alias for verifyIntegrity.
     */
    public function verifyChainIntegrity(): array
    {
        $res = $this->verifyIntegrity();
        return [
            'status' => $res['is_valid'] ? 'valid' : 'corrupted',
            'checked_count' => AuditLog::count(),
            'broken_id' => $res['failed_at_id'],
            'message' => $res['is_valid'] ? 'Chain verified.' : "Corrupted at ID {$res['failed_at_id']}.",
        ];
    }

    /**
     * Recursively sort array keys to guarantee canonical JSON sorting.
     */
    public static function sortKeys(array $payload): array
    {
        ksort($payload);
        foreach ($payload as $key => &$value) {
            if (is_array($value)) {
                $value = self::sortKeys($value);
            }
        }
        return $payload;
    }

    /**
     * Compute SHA-256 hash using exact formula:
     * hash('sha256', $prevHash . '|' . $timestamp . '|' . $actorId . '|' . $action . '|' . $targetType . '|' . $targetId . '|' . json_encode($payload, JSON_SORT_KEYS))
     */
    public static function calculateHash(
        string $previousHash,
        string $timestamp,
        string $actorId,
        string $action,
        string $targetType,
        string $targetId,
        array $payload
    ): string {
        $jsonPayload = json_encode(self::sortKeys($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $rawString = $previousHash . '|' . $timestamp . '|' . $actorId . '|' . $action . '|' . $targetType . '|' . $targetId . '|' . $jsonPayload;

        return hash('sha256', $rawString);
    }
}
