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
        Schema::table('student_verification_documents', function (Blueprint $table) {
            // Set once the image is purged, 30 days after a decision - the
            // decision itself (status, reviewed_at, reviewed_by, admin_notes)
            // is kept forever; only the image file is removed from disk.
            $table->timestamp('file_deleted_at')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_verification_documents', function (Blueprint $table) {
            $table->dropColumn('file_deleted_at');
        });
    }
};
