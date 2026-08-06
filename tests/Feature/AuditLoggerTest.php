<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected AuditLoggerService $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogger = app(AuditLoggerService::class);
    }

    public function test_audit_logger_records_entries_and_detects_db_payload_tampering_at_failed_id(): void
    {
        $actor = User::factory()->create(['role' => 'student']);

        $logs = [];
        for ($i = 1; $i <= 5; $i++) {
            $logs[$i] = $this->auditLogger->recordAction(
                $actor,
                "ACTION_{$i}",
                'Listing',
                (string) $i,
                ['step' => $i, 'original_data' => "value_{$i}"]
            );
        }

        // Before tampering, integrity check must pass
        $initialResult = $this->auditLogger->verifyIntegrity();
        $this->assertTrue($initialResult['is_valid']);
        $this->assertNull($initialResult['failed_at_id']);

        // Manually modify payload of entry #3 directly in DB
        $entry3 = $logs[3];
        AuditLog::where('id', $entry3->id)->update([
            'payload' => json_encode(['step' => 3, 'tampered' => 'malicious_modification']),
        ]);

        // Assert verifyIntegrity fails at ID 3
        $tamperedResult = $this->auditLogger->verifyIntegrity();
        $this->assertFalse($tamperedResult['is_valid']);
        $this->assertEquals($entry3->id, $tamperedResult['failed_at_id']);
    }
}
