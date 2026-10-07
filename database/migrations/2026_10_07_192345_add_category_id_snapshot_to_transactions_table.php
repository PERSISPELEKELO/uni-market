<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A snapshot of the listing's category at the time of the transaction,
     * not a live join - so if a listing is ever recategorised later, past
     * BI figures still reflect what was actually true when the sale
     * happened (the same reasoning as the existing transaction_mode
     * snapshot column). Nullable and backfilled rather than required, so
     * this never breaks an existing row.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('listing_id')->constrained()->nullOnDelete();
        });

        // A correlated-subquery UPDATE rather than a multi-table UPDATE JOIN,
        // so this works identically on MySQL (production) and SQLite (tests).
        DB::statement('
            UPDATE transactions
            SET category_id = (SELECT category_id FROM listings WHERE listings.id = transactions.listing_id)
            WHERE category_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
