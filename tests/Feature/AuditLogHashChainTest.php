<?php

declare(strict_types=1);

namespace Tests\Feature;

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

    public function test_chain_stays_verifiable_after_an_actors_account_is_permanently_deleted(): void
    {
        $actor = User::factory()->create(['role' => 'student']);

        $this->auditLogger->recordAction($actor, 'USER_REGISTERED', 'User', (string) $actor->id, []);
        $this->auditLogger->recordAction($actor, 'USER_LOGIN', 'User', (string) $actor->id, []);

        $actor->delete();

        $result = $this->auditLogger->verifyIntegrity();

        $this->assertTrue(
            $result['is_valid'],
            'The chain should stay verifiable once the actor is gone, because actor_id_snapshot - unlike '
            .'the actor_id foreign key - is never touched by ON DELETE SET NULL.'
        );
    }

    public function test_a_legacy_row_with_no_snapshot_correctly_stays_broken_if_its_actor_is_deleted(): void
    {
        $actor = User::factory()->create(['role' => 'student']);
        $log = $this->auditLogger->recordAction($actor, 'USER_REGISTERED', 'User', (string) $actor->id, []);

        // Simulates a row written before actor_id_snapshot existed.
        $log->forceFill(['actor_id_snapshot' => null, 'actor_name_snapshot' => null])->save();

        $actor->delete();

        $result = $this->auditLogger->verifyIntegrity();

        $this->assertFalse(
            $result['is_valid'],
            'A pre-snapshot row has no immutable record of who the actor was, so once that actor is '
            .'deleted there is no way to recompute the original hash - this is the expected, correct '
            .'behaviour of a hash chain, not a bug: it is detecting that something about the record '
            .'genuinely changed.'
        );
    }

    public function test_actor_display_name_shows_the_live_name_while_the_account_still_exists(): void
    {
        $actor = User::factory()->create(['name' => 'Chileshe Mwansa']);
        $log = $this->auditLogger->recordAction($actor, 'USER_LOGIN', 'User', (string) $actor->id, []);

        $this->assertSame('Chileshe Mwansa', $log->actorDisplayName());
    }

    public function test_actor_display_name_falls_back_to_the_snapshot_once_the_account_is_deleted(): void
    {
        $actor = User::factory()->create(['name' => 'Chileshe Mwansa']);
        $log = $this->auditLogger->recordAction($actor, 'USER_LOGIN', 'User', (string) $actor->id, []);

        $actor->delete();

        $this->assertSame('Chileshe Mwansa (deleted)', $log->fresh()->actorDisplayName());
    }

    public function test_actor_display_name_says_system_for_a_genuine_system_action(): void
    {
        $log = $this->auditLogger->recordAction(null, 'SYSTEM_SWEEP', 'System', '0', []);

        $this->assertSame('System', $log->actorDisplayName());
    }
}
