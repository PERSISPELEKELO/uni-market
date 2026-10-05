<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Hot Deal is derived, not stored: a listing is one whenever
     * previous_price is set and genuinely higher than the current price
     * (see Listing::isHotDeal()). Deliberately just a single snapshot of the
     * immediately-previous price, not a full multi-row history table - every
     * price edit either sets both columns (genuine reduction), clears both
     * (price increased, so any current deal is no longer valid) or leaves
     * them untouched (price unchanged) - see ListingForm::update().
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('previous_price', 10, 2)->nullable()->after('price');
            $table->timestamp('price_dropped_at')->nullable()->after('previous_price');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['previous_price', 'price_dropped_at']);
        });
    }
};
