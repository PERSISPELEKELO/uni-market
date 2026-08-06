<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\Dispute;
use App\Models\Message;
use App\Models\AuditLog;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Campus Categories
        $categories = [
            ['name' => 'Textbooks & Study', 'slug' => 'textbooks-study'],
            ['name' => 'Electronics & Laptops', 'slug' => 'electronics-laptops'],
            ['name' => 'Dorm Gear & Appliances', 'slug' => 'dorm-gear-appliances'],
            ['name' => 'Apparel & Fashion', 'slug' => 'apparel-fashion'],
            ['name' => 'Bikes & Campus Transport', 'slug' => 'bikes-campus-transport'],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[$cat['slug']] = Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 2. Create Student & Admin Users
        $chileshe = User::firstOrCreate(
            ['email' => 'chileshe@student.zut.zm'],
            [
                'name' => 'Chileshe Mwansa',
                'student_id' => '2024198273',
                'phone_number' => '+260971122334',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'is_verified' => true,
            ]
        );

        $mwamba = User::firstOrCreate(
            ['email' => 'mwamba@student.zut.zm'],
            [
                'name' => 'Mwamba Bwalya',
                'student_id' => '2024883912',
                'phone_number' => '+260965544332',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'is_verified' => true,
            ]
        );

        $kabwe = User::firstOrCreate(
            ['email' => 'kabwe@student.zut.zm'],
            [
                'name' => 'Kabwe Phiri',
                'student_id' => '2024551209',
                'phone_number' => '+260950998877',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'is_verified' => true,
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@zut.zm'],
            [
                'name' => 'Campus Moderator',
                'student_id' => 'ADMIN-001',
                'phone_number' => '+260970000000',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_verified' => true,
            ]
        );

        // 3. Create Sample Listings
        $listingsData = [
            [
                'user_id' => $chileshe->id,
                'category_id' => $categoryModels['textbooks-study']->id,
                'title' => 'Calculus Early Transcendentals 9th Edition',
                'description' => 'Clean condition Stewart Calculus textbook used for MAT1100. Minimal highlighting on chapter 3, bindings intact.',
                'price' => 450.00,
                'condition' => 'like_new',
                'status' => 'active',
                'images' => [],
            ],
            [
                'user_id' => $mwamba->id,
                'category_id' => $categoryModels['electronics-laptops']->id,
                'title' => 'MacBook Air M1 256GB Space Gray',
                'description' => 'Battery health 89%, comes with original USB-C charger and protective sleeve. Fast laptop for computer science or engineering.',
                'price' => 7500.00,
                'condition' => 'good',
                'status' => 'active',
                'images' => [],
            ],
            [
                'user_id' => $kabwe->id,
                'category_id' => $categoryModels['dorm-gear-appliances']->id,
                'title' => 'Mini Dorm Refrigerator 45L',
                'description' => 'Compact energy-efficient mini fridge. Fits under standard student room desk. Works perfectly and chilled ice tray included.',
                'price' => 1200.00,
                'condition' => 'good',
                'status' => 'pending',
                'images' => [],
            ],
            [
                'user_id' => $chileshe->id,
                'category_id' => $categoryModels['bikes-campus-transport']->id,
                'title' => 'Giant Mountain Bike 21-Speed',
                'description' => 'Lightweight aluminum frame bike with front shock suspension. Perfect for navigating campus grounds quickly.',
                'price' => 1800.00,
                'condition' => 'fair',
                'status' => 'active',
                'images' => [],
            ],
        ];

        $createdListings = [];
        foreach ($listingsData as $lData) {
            $createdListings[] = Listing::create($lData);
        }

        // 4. Create Escrow Transactions
        $tx1 = Transaction::create([
            'listing_id' => $createdListings[2]->id, // Mini Fridge
            'buyer_id' => $chileshe->id,
            'seller_id' => $kabwe->id,
            'amount' => 1200.00,
            'status' => 'disputed',
        ]);

        $tx2 = Transaction::create([
            'listing_id' => $createdListings[0]->id, // Calculus Textbook
            'buyer_id' => $mwamba->id,
            'seller_id' => $chileshe->id,
            'amount' => 450.00,
            'status' => 'completed',
        ]);

        // 5. Create Dispute with Python AI Analysis Scores
        $dispute = Dispute::create([
            'transaction_id' => $tx1->id,
            'raised_by' => $chileshe->id,
            'reason' => 'The mini fridge cooling fan has an unmentioned loud buzzing noise when plugged in, and small cosmetic dent on rear left corner.',
            'status' => 'open',
            'ai_sentiment_score' => -0.72,
            'ai_confidence_score' => 0.91,
            'ai_suggested_resolution' => 'PARTIAL_REFUND_OR_RETURN',
            'ai_analysis_summary' => 'NLP sentiment analysis identified high dissatisfaction regarding undisclosed mechanical noise. Recommendation: Approve partial refund of K200 or allow buyer return.',
        ]);

        // 6. Create Messages Thread
        Message::create([
            'listing_id' => $createdListings[2]->id,
            'transaction_id' => $tx1->id,
            'sender_id' => $chileshe->id,
            'receiver_id' => $kabwe->id,
            'message' => 'Hi Kabwe, I just plugged in the fridge and noticed a buzzing sound from the fan. Can we meet near October Hostel?',
            'is_read' => true,
        ]);

        Message::create([
            'listing_id' => $createdListings[2]->id,
            'transaction_id' => $tx1->id,
            'sender_id' => $kabwe->id,
            'receiver_id' => $chileshe->id,
            'message' => 'Hello Chileshe, it was working quiet when I tested it in my room yesterday. Let us review the item together.',
            'is_read' => false,
        ]);

        // 7. Seed Security Audit Logs
        $auditLogger = app(\App\Services\AuditLoggerService::class);

        $auditLogger->recordAction(
            $chileshe,
            'USER_REGISTERED',
            'User',
            (string) $chileshe->id,
            ['email' => $chileshe->email, 'is_verified' => true]
        );

        $auditLogger->recordAction(
            $chileshe,
            'TRANSACTION_INITIATED',
            'Transaction',
            (string) $tx1->id,
            ['amount' => 1200.00, 'seller_id' => $kabwe->id]
        );

        $auditLogger->recordAction(
            $chileshe,
            'DISPUTE_RAISED',
            'Dispute',
            (string) $dispute->id,
            ['reason' => $dispute->reason, 'ai_sentiment_score' => -0.72]
        );
    }
}
