<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A snapshot of the listing's category transaction_mode at the moment
     * this transaction was created. Deliberately not a live lookup: if an
     * admin later changes a category's mode, transactions already in
     * progress or completed under the old mode must keep behaving the way
     * they started - never retroactively. Every existing row predates DIRECT
     * categories, so they all default to INSPECTION, which is what actually
     * applied to them.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transaction_mode', 20)->default('INSPECTION')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_mode');
        });
    }
};
