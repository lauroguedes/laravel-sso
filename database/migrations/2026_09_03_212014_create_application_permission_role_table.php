<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which permissions a role grants. Both sides already belong to the same
     * application, so the pivot carries no application column of its own.
     */
    public function up(): void
    {
        Schema::create('application_permission_role', function (Blueprint $table) {
            $table->foreignId('application_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_permission_id')->constrained()->cascadeOnDelete();

            $table->primary(
                ['application_role_id', 'application_permission_id'],
                'application_permission_role_primary'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_permission_role');
    }
};
