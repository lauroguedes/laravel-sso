<?php

declare(strict_types=1);

namespace App\Models;

use App\Events\UserApplicationAccessRevoked;
use App\Events\UserApplicationRoleChanged;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's access to one application, and the role they hold there.
 *
 * This is a model rather than a bare pivot because access is a first class
 * record: it is granted, revoked and audited, and it exists independently of
 * whether a role has been assigned yet.
 *
 * @property int $id
 * @property string $application_id
 * @property int $user_id
 * @property int|null $application_role_id
 */
#[Fillable(['application_id', 'user_id', 'application_role_id'])]
class ApplicationUser extends Model
{
    protected $table = 'application_user';

    /**
     * The application access was granted to.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * The user the access belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Assign, change or clear the role held in this application.
     *
     * The event is raised here rather than at the call site so that every
     * route to changing a user's privileges is audited, matching how
     * ApplicationManager owns the application lifecycle events.
     */
    public function assignRole(?ApplicationRole $role): void
    {
        $previous = $this->role;

        if ($previous?->getKey() === $role?->getKey()) {
            return;
        }

        $this->forceFill(['application_role_id' => $role?->getKey()])->save();

        $this->setRelation('role', $role);

        UserApplicationRoleChanged::dispatch($this, $previous);
    }

    /**
     * Withdraw the user's access to the application.
     */
    public function revoke(): void
    {
        $this->delete();

        UserApplicationAccessRevoked::dispatch($this);
    }

    /**
     * The role held in this application, if one has been assigned.
     *
     * @return BelongsTo<ApplicationRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(ApplicationRole::class, 'application_role_id');
    }
}
