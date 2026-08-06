<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 50);
            $table->string('target_id', 100);
            $table->text('reason');
            $table->json('evidence_urls')->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, UNDER_REVIEW, UPHELD, OVERTURNED
            $table->text('governance_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('appeals');
    }
};
