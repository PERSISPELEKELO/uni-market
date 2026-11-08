<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a FREE, SIMULATED escrow layer on top of the existing handover
 * workflow: no real payment gateway, no real money. "HELD" means a
 * simulated payment is sitting in a simulated escrow balance; "RELEASED"
 * means it was simulated as paid out to the seller; "REFUNDED" means it
 * was simulated as returned to the buyer. Every existing transaction
 * lifecycle, status value and table is left untouched - this only adds
 * columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('HELD')->after('amount');
            $table->timestamp('funds_held_at')->nullable()->after('payment_status');
            $table->timestamp('funds_released_at')->nullable()->after('funds_held_at');
            $table->timestamp('refunded_at')->nullable()->after('funds_released_at');
        });

        // Backfill existing rows so historical figures stay consistent with
        // what already happened, the same approach as the earlier category_id
        // snapshot migration - a plain UPDATE, identical on MySQL and SQLite.
        DB::statement("UPDATE transactions SET payment_status = 'RELEASED', funds_released_at = COALESCE(completed_at, updated_at) WHERE UPPER(status) = 'COMPLETED'");
        DB::statement("UPDATE transactions SET payment_status = 'REFUNDED', refunded_at = updated_at WHERE UPPER(status) = 'REFUNDED'");
        DB::statement("UPDATE transactions SET funds_held_at = created_at WHERE payment_status = 'HELD'");
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'funds_held_at', 'funds_released_at', 'refunded_at']);
        });
    }
};
