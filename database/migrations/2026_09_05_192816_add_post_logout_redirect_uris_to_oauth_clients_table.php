<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a client may send the browser after RP-initiated logout.
 *
 * A separate list from "redirect_uris" on purpose. Redirect URIs receive
 * authorization codes, so an operator should be adding as few of them as
 * possible; reusing that list for logout landing pages would push people to
 * widen the most security-sensitive setting an application has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->json('post_logout_redirect_uris')->nullable()->after('redirect_uris');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('post_logout_redirect_uris');
        });
    }
};
