<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLoggerService;
use App\Services\ReputationExporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReputationExportTest extends TestCase
{
    use RefreshDatabase;

    protected ReputationExporterService $exporterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporterService = app(ReputationExporterService::class);
    }

    public function test_reputation_export_payload_signature_verifies_successfully_with_public_key(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_verified' => true]);

        $export = $this->exporterService->exportUserData($user);

        $this->assertEquals('1.0', $export['version']);
        $this->assertEquals('University Marketplace Platform', $export['issuer']);
        $this->assertEquals('SHA256withRSA', $export['algorithm']);
        $this->assertNotEmpty($export['signature']);
        $this->assertNotEmpty($export['public_key']);

        $jsonPayload = json_encode(AuditLoggerService::sortKeys($export['data']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $binarySignature = base64_decode($export['signature']);
        $publicKeyPem = $export['public_key'];

        $result = openssl_verify($jsonPayload, $binarySignature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        $this->assertEquals(1, $result, 'openssl_verify must return 1 for authentic RSA signed payload');
    }
}
