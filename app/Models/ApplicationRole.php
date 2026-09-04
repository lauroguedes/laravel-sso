<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApplicationRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A role defined by one application, grouping that application's permissions.
 *
 * Roles are scoped to their application: "Admin" in the reporting application
 * is unrelated to "Admin" in billing, and neither has any bearing on this
 * server's own platform roles.
 *
 * @property int $id
 * @property string $application_id
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['name', 'description'])]
class ApplicationRole extends Model
{
    /** @use HasFactory<ApplicationRoleFactory> */
    use HasFactory;

    /**
     * The permissions this role grants.
     *
     * @return BelongsToMany<ApplicationPermission, $this>
     */
    public function permissions(): BelongsToMany
    {
        /*
         * Named explicitly: Laravel would otherwise guess
         * "application_permission_application_role" from the two model names.
         */
        return $this->belongsToMany(
            ApplicationPermission::class,
            'application_permission_role'
        );
    }

    /**
     * The access grants that currently hold this role.
     *
     * @return HasMany<ApplicationUser, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(ApplicationUser::class);
    }
}
