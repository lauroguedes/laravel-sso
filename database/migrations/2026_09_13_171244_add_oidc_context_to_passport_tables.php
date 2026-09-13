<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The nonce and sign-in time an ID Token repeats, recorded on the code and each access token.
     */
    public function up(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->text('nonce')->nullable();
            $table->unsignedBigInteger('auth_time')->nullable();
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->text('nonce')->nullable();
            $table->unsignedBigInteger('auth_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['nonce', 'auth_time']);
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->dropColumn(['nonce', 'auth_time']);
        });
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
