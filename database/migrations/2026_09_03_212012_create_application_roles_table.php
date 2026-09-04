<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Roles belong to one application. A role named "Admin" in the reporting
     * application is a different record from "Admin" in billing, which is what
     * keeps one application's authorization out of another's.
     *
     * These are deliberately not spatie roles. Spatie's teams feature could
     * model this, but its role checks read a process-wide "current team",
     * which is the wrong shape for a server that mints claims for arbitrary
     * applications within one request. This server also never enforces these
     * permissions — it only reports them to the application, which does.
     */
    public function up(): void
    {
        Schema::create('application_roles', function (Blueprint $table) {
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
        Schema::dropIfExists('application_roles');
    }
};
