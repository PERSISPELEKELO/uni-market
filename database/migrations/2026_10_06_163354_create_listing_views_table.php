<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per genuine view. De-duplication (at most once per viewer or
     * session per listing per 24 hours, never the seller viewing their own
     * listing) is enforced in ListingShow::mount(), not here - this table
     * just records what actually happened.
     */
    public function up(): void
    {
        Schema::create('listing_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_hash', 64); // sha256 of the session id - never the raw id
            $table->timestamp('viewed_at');

            $table->index(['listing_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_views');
    }
};
