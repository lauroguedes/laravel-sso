<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let the audit trail name an application as its subject.
     *
     * The activity log package declares its morph columns as integers, but an
     * application is a Passport client keyed by a UUID. SQLite never enforced
     * the column type, so the mismatch only surfaced on MySQL, which refuses to
     * truncate the UUID, and PostgreSQL would refuse the insert outright. Users
     * keep integer keys, so the column has to hold both, and 36 characters fits
     * either.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('subject_id', 36)->nullable()->change();
        });
    }

    /**
     * Deliberately empty: an integer column cannot hold the UUIDs now stored,
     * and rolling back further drops the table anyway.
     */
    public function down(): void
    {
        //
    }
};
