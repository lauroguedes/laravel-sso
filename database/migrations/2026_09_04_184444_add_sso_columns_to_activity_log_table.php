<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The activity log covers actor, event, target, timestamp and metadata on
     * its own. An audit trail for an Identity Provider needs three more things
     * as real columns rather than buried in the properties JSON, because every
     * one of them is something an administrator filters by.
     *
     * "application_id" is nullable and not a foreign key: an audit record must
     * outlive the application it describes, so that disabling or removing one
     * does not erase the history of what was done to it.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->uuid('application_id')->nullable()->after('causer_id')->index();
            $table->string('ip_address', 45)->nullable()->after('properties');
            $table->text('user_agent')->nullable()->after('ip_address');

            $table->index('created_at');

            /*
             * The audit search matches an address exactly and an event by
             * prefix, both of which these serve.
             */
            $table->index('event');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['event']);
            $table->dropIndex(['ip_address']);
            $table->dropColumn(['application_id', 'ip_address', 'user_agent']);
        });
    }
};
