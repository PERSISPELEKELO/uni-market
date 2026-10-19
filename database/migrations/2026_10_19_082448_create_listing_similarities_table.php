<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precomputed "Similar items" results - content-based (TF-IDF over
     * title + description + category) cosine similarity, refreshed nightly
     * by recommendations:refresh-similarity and immediately whenever a
     * listing is created or edited. Storing the top matches here (rather
     * than computing TF-IDF against the whole catalogue on every page
     * view) is what keeps a listing page fast regardless of catalogue size.
     */
    public function up(): void
    {
        Schema::create('listing_similarities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('similar_listing_id')->constrained('listings')->cascadeOnDelete();
            $table->float('score');
            $table->timestamp('computed_at');

            $table->unique(['listing_id', 'similar_listing_id']);
            $table->index(['listing_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_similarities');
    }
};
