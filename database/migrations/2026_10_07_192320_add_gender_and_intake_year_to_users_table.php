<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: `year_of_study` (added in an earlier migration) is left
     * exactly as it is - this never drops or renames it. `intake_year` is a
     * new, optional way to express the same idea that the User model can
     * compute a year-of-study from; a nullable column with no default keeps
     * every existing account working unchanged, and `gender` gets a safe
     * default so existing rows don't need backfilling.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('gender', ['female', 'male', 'undisclosed'])->default('undisclosed')->after('school');
            $table->unsignedSmallInteger('intake_year')->nullable()->after('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'intake_year']);
        });
    }
};
