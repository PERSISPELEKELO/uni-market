<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A reservation is a buyer expressing interest in a listing. Several buyers
     * may reserve the same listing at once (it stays publicly visible with a
     * count) until the seller picks one, at which point that reservation
     * becomes the Transaction and the rest are cancelled.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active, selected, cancelled
            $table->timestamps();

            $table->unique(['listing_id', 'buyer_id']);
            $table->index(['listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
