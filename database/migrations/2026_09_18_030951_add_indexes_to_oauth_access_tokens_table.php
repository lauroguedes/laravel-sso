<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Sessions page lists the tokens that are still usable: not revoked, not
 * expired, newest first, and often narrowed to one application. Passport
 * indexes only user_id, so each of those read a table that grows with every
 * sign-in. Only MySQL indexes a foreign key on its own, so client_id is named
 * here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->index(['revoked', 'expires_at']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->dropIndex(['revoked', 'expires_at']);
            $table->dropIndex(['client_id']);
        });
    }
};
