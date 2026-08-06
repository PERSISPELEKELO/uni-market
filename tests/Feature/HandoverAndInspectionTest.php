<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HandoverVerificationService;
use App\Services\InspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HandoverAndInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected HandoverVerificationService $handoverService;
    protected InspectionService $inspectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handoverService = app(HandoverVerificationService::class);
        $this->inspectionService = app(InspectionService::class);
    }

    public function test_seller_can_verify_valid_handover_code_and_start_inspection_timer(): void
    {
        Carbon::setTestNow(now());

        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Laptops', 'slug' => 'laptops']);

        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'MacBook Pro M2',
            'description' => '16-inch M2 Pro 512GB space gray',
            'price' => 15000.00,
            'condition' => 'like_new',
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 15000.00,
            'status' => 'RESERVED',
            'inspection_period_hours' => 48,
        ]);

        // Generate 6-digit code
        $code = $this->handoverService->generateHandoverCode($transaction);
        $this->assertEquals(6, strlen($code));

        // Seller verifies handover code via API
        $response = $this->actingAs($seller)->postJson("/api/v1/transactions/{$transaction->id}/verify-handover", [
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'ITEM_INSPECTION');

        $transaction->refresh();
        $this->assertEquals('ITEM_INSPECTION', $transaction->status);
        $this->assertNotNull($transaction->handed_over_at);
        $this->assertNotNull($transaction->inspection_ends_at);

        // Assert inspection_ends_at is set to exactly +48 hours from handed_over_at
        $expectedInspectionEnd = $transaction->handed_over_at->copy()->addHours(48);
        $this->assertEquals($expectedInspectionEnd->toIso8601String(), $transaction->inspection_ends_at->toIso8601String());
    }

    public function test_invalid_handover_code_increments_attempts_and_blocks_after_max_tries(): void
    {
        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones']);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'iPhone 14',
            'description' => '128GB midnight',
            'price' => 6000.00,
            'condition' => 'good',
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 6000.00,
            'status' => 'RESERVED',
        ]);

        $this->handoverService->generateHandoverCode($transaction);

        // Submit incorrect codes up to 5 attempts
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->actingAs($seller)->postJson("/api/v1/transactions/{$transaction->id}/verify-handover", [
                'code' => '000000',
            ]);
            $response->assertStatus(422);
            $this->assertEquals($attempt, $transaction->fresh()->handover_attempts);
        }

        // 6th attempt should block with maximum attempts exceeded error
        $blockedResponse = $this->actingAs($seller)->postJson("/api/v1/transactions/{$transaction->id}/verify-handover", [
            'code' => '000000',
        ]);

        $blockedResponse->assertStatus(422)
            ->assertJsonPath('message', 'Maximum handover verification attempts exceeded.');
    }

    public function test_buyer_can_raise_dispute_within_inspection_window(): void
    {
        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Gaming Console',
            'description' => 'PS5 digital edition',
            'price' => 9000.00,
            'condition' => 'good',
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 9000.00,
            'status' => 'HANDED_OVER',
            'handed_over_at' => now(),
            'inspection_period_hours' => 48,
            'inspection_ends_at' => now()->addHours(48),
        ]);

        $response = $this->actingAs($buyer)->postJson("/api/v1/transactions/{$transaction->id}/dispute", [
            'reason' => 'Console shuts down abruptly after 20 minutes of play due to overheating.',
            'evidence_urls' => ['https://evidence.example.com/video.mp4'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertEquals('DISPUTED', $transaction->fresh()->status);
        $this->assertDatabaseHas('disputes', [
            'transaction_id' => $transaction->id,
            'raised_by' => $buyer->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'POST_PURCHASE_DISPUTE_RAISED',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
        ]);
    }

    public function test_buyer_cannot_raise_dispute_after_inspection_window_expires(): void
    {
        $startTime = Carbon::parse('2026-08-01 10:00:00');
        Carbon::setTestNow($startTime);

        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Wireless Headphones',
            'description' => 'Noise canceling headphones',
            'price' => 1200.00,
            'condition' => 'like_new',
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 1200.00,
            'status' => 'HANDED_OVER',
            'handed_over_at' => $startTime,
            'inspection_period_hours' => 48,
            'inspection_ends_at' => (clone $startTime)->addHours(48),
        ]);

        // Travel forward 49 hours (past the 48h inspection window)
        Carbon::setTestNow((clone $startTime)->addHours(49));

        $response = $this->actingAs($buyer)->postJson("/api/v1/transactions/{$transaction->id}/dispute", [
            'reason' => 'Late dispute attempt after window expired',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Inspection period of 48 hours has lapsed. Disputes can no longer be raised automatically.');
    }

    public function test_auto_complete_command_finalizes_expired_inspections(): void
    {
        $startTime = Carbon::parse('2026-08-01 10:00:00');
        Carbon::setTestNow($startTime);

        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Books', 'slug' => 'books']);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Physics Vol 1',
            'description' => 'University Physics Textbook',
            'price' => 300.00,
            'condition' => 'good',
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 300.00,
            'status' => 'HANDED_OVER',
            'handed_over_at' => $startTime,
            'inspection_period_hours' => 48,
            'inspection_ends_at' => (clone $startTime)->addHours(48),
        ]);

        // Fast-forward past inspection_ends_at (+50 hours)
        Carbon::setTestNow((clone $startTime)->addHours(50));

        // Execute artisan command
        $this->artisan('transactions:auto-complete')
            ->expectsOutputToContain('Successfully auto-completed 1 transaction(s).')
            ->assertExitCode(0);

        $transaction->refresh();
        $this->assertEquals('COMPLETED', $transaction->status);
        $this->assertNotNull($transaction->completed_at);
        $this->assertEquals('sold', $listing->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'TRANSACTION_AUTO_COMPLETED_POST_INSPECTION',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
        ]);
    }
}
