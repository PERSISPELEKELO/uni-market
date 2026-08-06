<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReputationExporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReputationExporterTest extends TestCase
{
    use RefreshDatabase;

    protected ReputationExporterService $exporterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporterService = app(ReputationExporterService::class);
    }

    public function test_reputation_exporter_generates_valid_asymmetrically_signed_package(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_verified' => true]);

        $export = $this->exporterService->exportUserData($user);

        $this->assertArrayHasKey('data', $export);
        $this->assertArrayHasKey('signature', $export);
        $this->assertArrayHasKey('public_key', $export);
        $this->assertEquals('SHA256withRSA', $export['algorithm']);

        $this->assertEquals($user->id, $export['data']['user_id']);
        $this->assertEquals('VERIFIED', $export['data']['institutional_verification_state']);
        $this->assertGreaterThanOrEqual(50, $export['data']['reputation_score']);
    }

    public function test_signature_verification_succeeds_for_authentic_package(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $export = $this->exporterService->exportUserData($user);

        $isValid = $this->exporterService->verifyExportSignature($export);

        $this->assertTrue($isValid);
    }

    public function test_signature_verification_fails_when_exported_data_is_tampered(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $export = $this->exporterService->exportUserData($user);

        // Modify score in payload without re-signing
        $export['data']['reputation_score'] = 100;

        $isValid = $this->exporterService->verifyExportSignature($export);

        $this->assertFalse($isValid);
    }

    public function test_user_can_export_and_verify_reputation_via_api(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        // Export
        $exportResponse = $this->actingAs($user)->getJson('/api/reputation/export');
        $exportResponse->assertStatus(200)
            ->assertJsonStructure(['version', 'issuer', 'data', 'signature', 'algorithm', 'public_key']);

        $package = $exportResponse->json();

        // Verify
        $verifyResponse = $this->postJson('/api/reputation/verify', $package);
        $verifyResponse->assertStatus(200)
            ->assertJsonPath('is_valid', true);
    }
}
