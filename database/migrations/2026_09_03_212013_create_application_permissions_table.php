<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The catalogue of capabilities an application recognises, such as
     * "reports.view". Permissions are grouped by roles and are reported to the
     * application in a token; this server does not enforce them.
     */
    public function up(): void
    {
        Schema::create('application_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('application_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_permissions');
    }
};
