<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppealStatus;
use App\Models\Appeal;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppealWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_appeal_and_resolve_overturned_restores_target_resource_and_logs_audit(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $governanceUser = User::factory()->create(['role' => 'governance_committee']);
        $category = Category::create(['name' => 'Textbooks', 'slug' => 'textbooks']);

        // Create a moderated/suspended listing owned by student
        $listing = Listing::create([
            'user_id' => $student->id,
            'category_id' => $category->id,
            'title' => 'Calculus 10th Edition',
            'description' => 'Sanctioned book listing requiring appeal',
            'price' => 45.00,
            'condition' => 'good',
            'status' => 'suspended',
            'images' => ['listings/sample.jpg'],
        ]);

        // Step 1: Submit appeal as student
        $submitResponse = $this->actingAs($student)->postJson('/api/v1/appeals', [
            'target_type' => 'Listing',
            'target_id' => (string) $listing->id,
            'reason' => 'Listing was suspended by error during automated keyword filter.',
            'evidence_urls' => ['https://evidence.example.com/receipt.png'],
        ]);

        $submitResponse->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $appealId = $submitResponse->json('data.id');
        $this->assertNotNull($appealId);

        // Step 2: Execute resolve endpoint as Governance user with OVERTURNED
        $resolveResponse = $this->actingAs($governanceUser)->postJson("/api/v1/governance/appeals/{$appealId}/resolve", [
            'outcome' => 'OVERTURNED',
            'governance_notes' => 'Restored listing after review of valid purchase receipt.',
        ]);

        $resolveResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'OVERTURNED');

        // Step 3: Verify target state restored (listing status marked active)
        $this->assertEquals('active', $listing->fresh()->status);

        // Step 4: Verify audit log generated
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'APPEAL_RESOLVED',
            'target_type' => 'Appeal',
            'target_id' => (string) $appealId,
            'actor_id' => $governanceUser->id,
        ]);
    }
}
