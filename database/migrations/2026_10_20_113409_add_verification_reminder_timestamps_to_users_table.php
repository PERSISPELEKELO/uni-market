<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set once each follow-up reminder to upload a student ID has
            // been sent, so the daily command never sends either one twice.
            // Both stay null forever once the student submits a document.
            $table->timestamp('student_verification_reminder_24h_sent_at')->nullable();
            $table->timestamp('student_verification_reminder_3d_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['student_verification_reminder_24h_sent_at', 'student_verification_reminder_3d_sent_at']);
        });
    }
};
