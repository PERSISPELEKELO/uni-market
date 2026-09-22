<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the student-identity verification workflow, kept separate from
     * `email_verified_at` (which only proves the member owns the email address)
     * and from `is_verified` (the public "Verified Student" badge, which now
     * only becomes true once an administrator approves a submitted document).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_verification_status', 30)->default('not_submitted')->after('is_verified');
            $table->timestamp('student_verification_submitted_at')->nullable()->after('student_verification_status');
            $table->timestamp('student_verification_reviewed_at')->nullable()->after('student_verification_submitted_at');
            $table->foreignId('student_verification_reviewed_by')->nullable()->after('student_verification_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('student_verification_rejection_reason')->nullable()->after('student_verification_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_verification_reviewed_by');
            $table->dropColumn([
                'student_verification_status',
                'student_verification_submitted_at',
                'student_verification_reviewed_at',
                'student_verification_rejection_reason',
            ]);
        });
    }
};
