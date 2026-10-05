<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DIRECT categories (e.g. Food & Beverages) skip the handover/inspection
     * flow entirely; INSPECTION categories keep it. Defaults to INSPECTION -
     * an unknown/misconfigured category should never accidentally bypass
     * protection (see Category::MODE_INSPECTION).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('transaction_mode', 20)->default('INSPECTION')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('transaction_mode');
        });
    }
};
