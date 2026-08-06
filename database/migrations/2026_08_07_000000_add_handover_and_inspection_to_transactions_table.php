<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('handover_code_hash', 60)->nullable();
            $table->string('handover_code_plain', 6)->nullable();
            $table->string('handover_otp_hash', 60)->nullable();
            $table->string('handover_otp_plain', 6)->nullable();
            $table->timestamp('handover_code_expires_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->unsignedInteger('handover_attempts')->default(0);

            $table->integer('inspection_period_hours')->default(48);
            $table->integer('inspection_duration_hours')->default(48);
            $table->timestamp('inspection_ends_at')->nullable();
            $table->timestamp('inspection_expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'handover_code_hash',
                'handover_code_plain',
                'handover_otp_hash',
                'handover_otp_plain',
                'handover_code_expires_at',
                'handed_over_at',
                'handover_attempts',
                'inspection_period_hours',
                'inspection_duration_hours',
                'inspection_ends_at',
                'inspection_expires_at',
                'completed_at',
            ]);
        });
    }
};
