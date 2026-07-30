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
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('users');
            $table->text('reason');
            $table->enum('status', ['open', 'under_review', 'resolved_buyer', 'resolved_seller'])->default('open');

            // Fields populated by Python NLP Microservice
            $table->float('ai_sentiment_score')->nullable(); // -1.0 (Negative) to 1.0 (Positive)
            $table->float('ai_confidence_score')->nullable(); // e.g., 0.85 (85%)
            $table->string('ai_suggested_resolution')->nullable(); // e.g., "REFUND_BUYER"
            $table->text('ai_analysis_summary')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
