<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('saved_listings', function (Blueprint $table) {
            $table->timestamp('price_drop_notified_at')->nullable()->after('price_at_save');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_listings', function (Blueprint $table) {
            $table->dropColumn('price_drop_notified_at');
        });
    }
};
