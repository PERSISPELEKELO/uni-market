<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both nullable: existing accounts have neither, and BI simply excludes
     * them rather than forcing a value - see User::hasCompletedZutProfile().
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('year_of_study')->nullable()->after('business_type');
            $table->string('school', 40)->nullable()->after('year_of_study');
            $table->index('year_of_study');
            $table->index('school');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['year_of_study']);
            $table->dropIndex(['school']);
            $table->dropColumn(['year_of_study', 'school']);
        });
    }
};
