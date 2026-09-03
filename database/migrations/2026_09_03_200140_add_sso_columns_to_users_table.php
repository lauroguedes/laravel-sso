<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Users are disabled rather than deleted so that audit history keeps
     * pointing at a real actor. "disabled_at" doubles as the timestamp of when
     * access was withdrawn, which is more useful than a boolean flag.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('disabled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'last_login_at']);
        });
    }
};
