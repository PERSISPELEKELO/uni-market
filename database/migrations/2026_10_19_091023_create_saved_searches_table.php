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
     * A student's standing search criteria to be notified about - "term"
     * and "condition" mirror the same filters ListingIndex already applies
     * (Listing::scopeSearch() and the Listing::CONDITIONS keys), so matching
     * new listings against this later reuses that exact logic rather than
     * duplicating it. last_notified_at is the cursor the matching command
     * advances, so the same new listing is never reported twice.
     */
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('term')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condition')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_notified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
