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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // e.g., 'TRANSACTION_DISPUTED', 'LISTING_CREATED'
            $table->string('entity_type'); // e.g., 'App\Models\Transaction'
            $table->unsignedBigInteger('entity_id');
            $table->json('payload')->nullable(); // Stores before/after state
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent(); // Immutable: no updated_at column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
