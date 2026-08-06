<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppealStatus;
use App\Models\Appeal;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceTransparencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_transparency_metrics_endpoint_returns_correct_aggregate_metrics(): void
    {
        $actor = User::factory()->create(['role' => 'student']);
        app(AuditLoggerService::class)->recordAction($actor, 'TEST_EVENT', 'Listing', '1', ['info' => 'test']);

        Appeal::factory()->create(['status' => AppealStatus::PENDING]);
        Appeal::factory()->create(['status' => AppealStatus::OVERTURNED, 'resolved_at' => now()]);

        $response = $this->getJson('/api/v1/transparency/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.audit_chain_integrity.valid', true)
            ->assertJsonPath('data.appeals_statistics.total_submitted', 2)
            ->assertJsonPath('data.appeals_statistics.overturned_count', 1)
            ->assertJsonPath('data.appeals_statistics.overturn_percentage', 50);
    }

    public function test_audit_feed_anonymizes_actor_ids_and_scrubs_pii(): void
    {
        $actor = User::factory()->create(['role' => 'student', 'email' => 'sensitive@student.edu']);

        app(AuditLoggerService::class)->recordAction(
            $actor,
            'USER_SENSITIVE_EVENT',
            'User',
            (string) $actor->id,
            [
                'email' => 'sensitive@student.edu',
                'student_id' => 'STU998877',
                'action_details' => 'Sanction appealed',
            ]
        );

        $response = $this->getJson('/api/v1/governance/audit-feed');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'timestamp',
                        'anonymized_actor_hash',
                        'actor_role',
                        'action',
                        'target_type',
                        'target_id',
                        'payload',
                        'previous_hash',
                        'current_hash',
                    ],
                ],
            ]);

        $firstItem = $response->json('data.0');

        $this->assertArrayNotHasKey('actor_id', $firstItem);
        $this->assertNotEmpty($firstItem['anonymized_actor_hash']);

        $this->assertEquals('[ANONYMIZED]', $firstItem['payload']['email']);
        $this->assertEquals('[ANONYMIZED]', $firstItem['payload']['student_id']);
        $this->assertEquals('Sanction appealed', $firstItem['payload']['action_details']);
    }
}
