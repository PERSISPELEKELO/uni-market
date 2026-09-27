<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * A hash chain needs immutable inputs. `actor_id` is a foreign key with
     * ON DELETE SET NULL, so permanently deleting a user who ever appears
     * as an actor changes that column after the fact - not tampering, but
     * enough to make re-verification recompute a different hash than the
     * one stored at write time. These two columns are plain, unconstrained
     * snapshots taken once, at write time, so they can never be mutated by
     * any later action on the `users` table: `actor_id_snapshot` is what
     * the hash is (re)computed from, and `actor_name_snapshot` lets the
     * admin UI still show who did something after that account is gone.
     * Rows written before this migration have neither column populated;
     * their hash was computed from the (then-current) actor_id and stays
     * verifiable unless that particular actor is later deleted, in which
     * case that historical entry's hash is - correctly - unverifiable,
     * exactly the way a hash chain is supposed to behave when something
     * about the record it covers changes after the fact.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id_snapshot')->nullable()->after('actor_id');
            $table->string('actor_name_snapshot')->nullable()->after('actor_id_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_id_snapshot', 'actor_name_snapshot']);
        });
    }
};
