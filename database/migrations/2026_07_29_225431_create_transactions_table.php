<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['initiated', 'completed', 'disputed', 'cancelled', 'refunded'])->default('initiated');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
