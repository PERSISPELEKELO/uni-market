<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A clean, minimal demo: the canonical categories plus exactly three
 * accounts (one seller, one buyer, one admin) - no listings, transactions,
 * messages, disputes, ratings, reservations or appeals. The demo itself
 * creates those live, so every number shown during it is genuine.
 *
 * Run via `php artisan demo:reset`, which also wipes the schema first and
 * clears uploaded listing/avatar/verification files. Local environment
 * only - see the same guard already used by InsightsDemoSeeder.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoSeeder only runs in the local environment. Skipped.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        $now = now();

        $admin = User::create([
            'name' => 'UniMarket Admin',
            'email' => 'admin@zut.zm',
            'student_id' => 'ADMIN-2026',
            'phone_number' => '+260970000000',
            'password' => Hash::make('Admin@2026'),
            'role' => 'admin',
            'is_verified' => false,
        ]);
        $admin->forceFill(['email_verified_at' => $now])->save();

        $seller = User::create([
            'name' => 'Test Seller',
            'email' => 'seller@student.zut.zm',
            'student_id' => '2026000001',
            'phone_number' => '+260971000001',
            'password' => Hash::make('Seller@2026'),
            'role' => 'student',
            'is_verified' => true,
            'student_verification_status' => User::STUDENT_VERIFICATION_VERIFIED,
            'student_verification_reviewed_at' => $now,
            'student_verification_reviewed_by' => $admin->id,
            'year_of_study' => 4,
            'school' => 'ict',
        ]);
        $seller->forceFill(['email_verified_at' => $now])->save();

        $buyer = User::create([
            'name' => 'Test Buyer',
            'email' => 'buyer@student.zut.zm',
            'student_id' => '2026000002',
            'phone_number' => '+260971000002',
            'password' => Hash::make('Buyer@2026'),
            'role' => 'student',
            'is_verified' => true,
            'student_verification_status' => User::STUDENT_VERIFICATION_VERIFIED,
            'student_verification_reviewed_at' => $now,
            'student_verification_reviewed_by' => $admin->id,
            'year_of_study' => 1,
            'school' => 'business',
        ]);
        $buyer->forceFill(['email_verified_at' => $now])->save();
    }
}
