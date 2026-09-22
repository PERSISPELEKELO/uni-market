<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rating a completed transaction's buyer and seller may each leave for
     * the other, once - one row per (transaction, rater). Which user is being
     * rated is always the transaction's *other* participant; it is never
     * taken from client input (see RatingService).
     */
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rated_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'rater_id']);
            $table->index('rated_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
