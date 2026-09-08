<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings an operator changes from the interface.
 *
 * Key and value rather than a column each: these are read as a set on every
 * request and written rarely, and a new one should not need a migration on a
 * running server. Defaults live in "config/sso.php", so a missing row means
 * "unchanged" rather than "empty".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            /*
             * JSON so a setting can be a list — the documentation links are
             * one — without a second table or a delimiter convention.
             */
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
