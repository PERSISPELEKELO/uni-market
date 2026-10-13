<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Unified, append-only activity log behind the seller/buyer insights
     * dashboards: one row per view, save, share or message. Immutable -
     * no updated_at. "Is this currently saved" lookups go through the
     * separate saved_listings table instead, which is a far better fit
     * for toggle-state than scanning an event log.
     */
    public function up(): void
    {
        Schema::create('listing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_hash', 64)->nullable(); // sha256 of the session id, for guest views
            $table->string('type', 20); // view, save, share, message
            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_id', 'type', 'created_at']);
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_events');
    }
};
