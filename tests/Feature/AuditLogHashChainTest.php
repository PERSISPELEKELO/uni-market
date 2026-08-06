<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogHashChainTest extends TestCase
{
    use RefreshDatabase;

    protected AuditLoggerService $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogger = app(AuditLoggerService::class);
    }

    public function test_genesis_log_uses_64_zeros_as_previous_hash(): void
    {
        $actor = User::factory()->create(['role' => 'student']);

        $log = $this->auditLogger->recordAction(
            $actor,
            'TEST_ACTION',
            'Listing',
            '101',
            ['details' => 'Initial test log']
        );

        $this->assertEquals(AuditLoggerService::GENESIS_HASH, $log->previous_hash);
        $this->assertEquals(64, strlen($log->previous_hash));
        $this->assertEquals(64, strlen($log->current_hash));
        $this->assertNotEmpty($log->uuid);
    }

    public function test_sequential_logs_form_an_unbroken_sha256_hash_chain(): void
    {
        $actor = User::factory()->create(['role' => 'student']);

        $log1 = $this->auditLogger->recordAction($actor, 'ACTION_1', 'Listing', '1', ['key' => 'val1']);
        $log2 = $this->auditLogger->recordAction($actor, 'ACTION_2', 'Transaction', '2', ['key' => 'val2']);
        $log3 = $this->auditLogger->recordAction($actor, 'ACTION_3', 'Appeal', '3', ['key' => 'val3']);

        $this->assertEquals(AuditLoggerService::GENESIS_HASH, $log1->previous_hash);
        $this->assertEquals($log1->current_hash, $log2->previous_hash);
        $this->assertEquals($log2->current_hash, $log3->previous_hash);
    }

    public function test_chain_integrity_verification_succeeds_for_untampered_logs(): void
    {
        $actor = User::factory()->create(['role' => 'student']);

        for ($i = 1; $i <= 5; $i++) {
            $this->auditLogger->recordAction($actor, "ACTION_{$i}", 'Target', (string) $i, ['step' => $i]);
        }

        $result = $this->auditLogger->verifyIntegrity();

        $this->assertTrue($result['is_valid']);
        $this->assertNull($result['failed_at_id']);
    }

    public function test_artisan_audit_verify_command_runs_successfully(): void
    {
        $actor = User::factory()->create(['role' => 'student']);
        $this->auditLogger->recordAction($actor, 'CLI_ACTION', 'User', (string) $actor->id, ['test' => true]);

        $this->artisan('audit:verify')
            ->expectsOutputToContain('SUCCESS: Audit log chain is intact and valid.')
            ->assertExitCode(0);
    }
}
