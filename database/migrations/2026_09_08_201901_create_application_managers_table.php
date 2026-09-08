<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Who may administer one application: change its configuration, rotate its
     * secret, define the roles its tokens carry, and read its audit trail.
     *
     * Deliberately not the "application_user" table beside it. That records
     * who may sign in to an application and what they hold there; this records
     * who looks after it. They are different facts about different people —
     * somebody maintains an application they never sign in to, and plenty of
     * people sign in to one they must never reconfigure — and a single table
     * would make each impossible to say without implying the other.
     */
    public function up(): void
    {
        Schema::create('application_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('application_id')->constrained('oauth_clients')->cascadeOnDelete();
            /*
             * Indexed explicitly. The composite unique below leads with
             * application_id, so it cannot serve "which applications does this
             * person look after", which is the question every one of their
             * requests asks.
             */
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_managers');
    }
};
