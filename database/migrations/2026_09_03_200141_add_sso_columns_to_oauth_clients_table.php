<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An Application is a Passport client, so its fields live on Passport's
     * own table rather than in a parallel schema. Passport already stores the
     * name, redirect URIs, grant types and revoked flag there.
     *
     * "scopes" is understood by Passport natively: Client::hasScope() treats a
     * missing attribute as "every scope allowed" and an array as a whitelist,
     * and Bridge\ScopeRepository filters requested scopes through it. The
     * column is simply absent from Passport's published migration.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->text('scopes')->nullable()->after('redirect_uris');
            $table->boolean('skips_authorization')->default(false)->after('revoked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['description', 'scopes', 'skips_authorization']);
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
