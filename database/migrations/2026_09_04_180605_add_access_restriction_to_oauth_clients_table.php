<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whether an application admits any authenticated user, or only those an
     * administrator has granted access to.
     *
     * The default is false, admitting everyone. An Identity Provider's usual
     * case is that everybody in the organisation may use an application, and
     * defaulting the other way would mean a newly registered application
     * turned nobody away only after somebody remembered to grant access —
     * a restriction that appears to work until the first real user arrives.
     * Restricting is the deliberate act, and the interface says so.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->boolean('restricts_access')->default(false)->after('skips_authorization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('restricts_access');
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
