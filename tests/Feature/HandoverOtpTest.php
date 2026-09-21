<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HandoverVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_remains_consistent_on_page_reload(): void
    {
        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Textbooks', 'slug' => 'textbooks']);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Calculus Early Transcendentals',
            'description' => 'Math Textbook 8th edition',
            'price' => 450.00,
            'condition' => 'good',
            'status' => 'active',
        ]);

        // Initiate purchase via controller endpoint
        $initResponse = $this->actingAs($buyer)->post("/listings/{$listing->id}/buy");
        $initResponse->assertRedirect();

        $transaction = Transaction::latest('id')->first();
        $initialOtp = $transaction->handover_otp_plain;

        $this->assertNotNull($initialOtp);
        $this->assertEquals(6, strlen($initialOtp));

        // Load tracker page 3 times
        for ($i = 1; $i <= 3; $i++) {
            $pageResponse = $this->actingAs($buyer)->get("/transactions-tracker/{$transaction->id}");
            $pageResponse->assertStatus(200);
            $pageResponse->assertSee($initialOtp);

            // Assert OTP in database has not changed across reloads
            $this->assertEquals($initialOtp, $transaction->fresh()->handover_otp_plain);
        }
    }

    public function test_seller_can_successfully_verify_matching_otp(): void
    {
        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Laptops', 'slug' => 'laptops']);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Dell XPS 13',
            'description' => 'Core i7 16GB RAM',
            'price' => 8500.00,
            'condition' => 'good',
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 8500.00,
            'status' => 'PENDING_MEETING',
        ]);

        $otp = app(HandoverVerificationService::class)->generateHandoverCode($transaction);

        // Submit exact 6-digit code as seller
        $response = $this->actingAs($seller)->post("/transactions/{$transaction->id}/verify-handover", [
            'otp' => $otp,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $transaction->refresh();
        $this->assertEquals('ITEM_INSPECTION', $transaction->status);
        $this->assertNotNull($transaction->handed_over_at);
        $this->assertNotNull($transaction->inspection_expires_at);
        $this->assertEquals(0, $transaction->handover_attempts);
    }

    public function test_attempt_counter_locks_out_only_after_five_failed_tries(): void
    {
        $buyer = User::factory()->create(['role' => 'student']);
        $seller = User::factory()->create(['role' => 'student']);
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $listing = Listing::create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Smart Watch',
            'description' => 'Fitness smartwatch',
            'price' => 1500.00,
            'condition' => 'like_new',
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 1500.00,
            'status' => 'PENDING_MEETING',
        ]);

        app(HandoverVerificationService::class)->generateHandoverCode($transaction);

        // Submit wrong code 5 times
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->actingAs($seller)->post("/transactions/{$transaction->id}/verify-handover", [
                'otp' => '000000',
            ]);

            $response->assertSessionHas('error');
            $this->assertEquals($attempt, $transaction->fresh()->handover_attempts);
        }

        // 6th attempt should block with lockout message
        $blockedResponse = $this->actingAs($seller)->post("/transactions/{$transaction->id}/verify-handover", [
            'otp' => '000000',
        ]);

        $blockedResponse->assertSessionHas('error', 'Maximum handover verification attempts exceeded.');
    }
}
