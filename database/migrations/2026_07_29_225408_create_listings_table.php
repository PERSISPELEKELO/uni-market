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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->enum('condition', ['new', 'like_new', 'good', 'fair'])->default('good');
            $table->enum('status', ['active', 'pending', 'sold', 'expired'])->default('active');
            $table->json('images')->nullable(); // Stores array of uploaded image paths
            $table->timestamps();
            $table->softDeletes(); // For 30-day auto-expiration handling
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
