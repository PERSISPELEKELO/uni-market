<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rating stays "visible" (counted in averages/breakdowns and shown on
     * profiles) unless an admin hides it after a review report - it is never
     * deleted, so the moderation trail and the audit log stay intact.
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->string('status', 20)->default('visible')->after('comment');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
