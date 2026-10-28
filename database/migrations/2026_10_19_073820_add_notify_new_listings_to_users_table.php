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
        Schema::table('users', function (Blueprint $table) {
            // Powers the marketplace empty state's "Get notified when items
            // arrive" secondary CTA - a real, honest opt-in rather than a
            // dead link: see NewListingPosted / CreateListing::persist().
            $table->boolean('notify_new_listings')->default(false)->after('theme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_new_listings');
        });
    }
};
