<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamp('timestamp')->useCurrent();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 50); // student, governance_committee, admin, system
            $table->string('action', 100);     // e.g., LISTING_SANCTIONED, APPEAL_DECIDED
            $table->string('target_type', 50); // Listing, User, Transaction, Appeal
            $table->string('target_id', 100);
            $table->json('payload');
            $table->string('previous_hash', 64);
            $table->string('current_hash', 64);
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('timestamp');
        });
    }

    public function down(): void {
        Schema::dropIfExists('audit_logs');
    }
};

