<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional public-profile fields. avatar_path lives on the public disk
     * (unlike verification documents, a profile photo is meant to be seen by
     * other students) and is served through the normal storage symlink.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('phone_number');
            $table->text('bio')->nullable()->after('avatar_path');
            $table->string('programme')->nullable()->after('bio');
            $table->string('business_type')->nullable()->after('programme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'bio', 'programme', 'business_type']);
        });
    }
};
