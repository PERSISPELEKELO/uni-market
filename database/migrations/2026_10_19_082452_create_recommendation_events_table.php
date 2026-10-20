<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified impression/click log for every recommendation carousel
     * (Recommended for you, Because you viewed X, Trending, Good deals,
     * Just listed, Similar items, Students also viewed) - the same
     * append-only-event-log convention as listing_events. Never
     * fabricated: an impression row is only ever written when a listing
     * genuinely renders inside that section, a click only when a student
     * genuinely follows a link from it. Powers the dissertation's
     * click-through-rate-per-section evaluation and the admin dashboard's
     * "recommendation click-through by section" chart.
     */
    public function up(): void
    {
        Schema::create('recommendation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_hash', 64)->nullable();
            $table->string('section', 40); // recommended_for_you, because_you_viewed, trending, good_deals, just_listed, similar_items, students_also_viewed
            $table->string('type', 10); // impression, click
            $table->timestamp('created_at')->useCurrent();

            $table->index(['section', 'type', 'created_at']);
            $table->index(['listing_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_events');
    }
};
