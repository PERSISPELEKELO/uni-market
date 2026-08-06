<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppealStatus;
use App\Models\Appeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceRBACTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_appeal_via_endpoint(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->postJson('/api/v1/appeals', [
            'target_type' => 'Listing',
            'target_id' => '105',
            'reason' => 'Listing removed in error during bulk moderation scan.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'PENDING');
    }

    public function test_student_cannot_resolve_appeals(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $appeal = Appeal::factory()->create([
            'user_id' => $student->id,
            'status' => AppealStatus::UNDER_REVIEW,
        ]);

        $response = $this->actingAs($student)->postJson("/api/v1/governance/appeals/{$appeal->id}/resolve", [
            'outcome' => 'UPHELD',
            'governance_notes' => 'Unauthorized attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_governance_committee_can_review_and_resolve_appeals(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $committeeMember = User::factory()->create(['role' => 'governance_committee']);
        $appeal = Appeal::factory()->create([
            'user_id' => $student->id,
            'status' => AppealStatus::PENDING,
        ]);

        // Resolve appeal
        $resolveResponse = $this->actingAs($committeeMember)->postJson("/api/v1/governance/appeals/{$appeal->id}/resolve", [
            'outcome' => 'OVERTURNED',
            'governance_notes' => 'Restored listing after review.',
        ]);

        $resolveResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'OVERTURNED');
    }

    public function test_admin_has_full_governance_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $appeal = Appeal::factory()->create(['status' => AppealStatus::PENDING]);

        $response = $this->actingAs($admin)->postJson("/api/v1/governance/appeals/{$appeal->id}/resolve", [
            'outcome' => 'UPHELD',
            'governance_notes' => 'Upheld sanction.',
        ]);

        $response->assertStatus(200);
    }
}
