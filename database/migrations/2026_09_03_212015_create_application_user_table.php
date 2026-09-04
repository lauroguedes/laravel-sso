<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A user's access to one application, and the single role they hold there.
     * Access and role are separate: a user may be granted access with no role,
     * which authenticates them without granting any capability.
     *
     * The role is nullable rather than a second pivot table because a user
     * holds at most one role per application. Should that ever need to become
     * several, this column moves to its own pivot.
     */
    public function up(): void
    {
        Schema::create('application_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('application_id')->constrained('oauth_clients')->cascadeOnDelete();
            /*
             * Both columns are indexed explicitly. The composite unique below
             * leads with application_id, so it cannot serve a lookup by user
             * alone, and neither SQLite nor PostgreSQL indexes a foreign key
             * for you — only MySQL does.
             */
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('application_role_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_user');
    }
};
