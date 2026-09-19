<?php

declare(strict_types=1);

namespace App\Oidc;

use Illuminate\Support\Facades\DB;

/**
 * The role and permissions a user holds in one application.
 *
 * They differ between applications, so they are only ever resolved for the
 * application asking, and describe that application alone: reporting must
 * never learn what a user may do in billing.
 */
class AuthorizationClaims
{
    /**
     * The claims this resolves.
     */
    public const CLAIMS = ['roles', 'permissions'];

    /**
     * The user's role and permissions in the application.
     *
     * A user with no access grant, or a grant with no role, reports empty
     * arrays rather than being omitted: the application can then tell "signed
     * in with nothing granted" apart from "authorization was not requested".
     *
     * This runs on every token issuance and every UserInfo request, so it is
     * one flat join returning strings rather than an Eloquent read that would
     * hydrate three models across three round trips.
     *
     * @return array{roles: array<int, string>, permissions: array<int, string>}
     */
    public function for(string $subject, string $clientId): array
    {
        $rows = DB::table('application_user')
            ->leftJoin('application_roles', 'application_roles.id', '=', 'application_user.application_role_id')
            ->leftJoin('application_permission_role', 'application_permission_role.application_role_id', '=', 'application_roles.id')
            ->leftJoin('application_permissions', 'application_permissions.id', '=', 'application_permission_role.application_permission_id')
            ->where('application_user.application_id', $clientId)
            ->where('application_user.user_id', $subject)
            ->get(['application_roles.name as role_name', 'application_permissions.name as permission_name']);

        $roleName = $rows->first()?->role_name;

        return [
            'roles' => $roleName === null ? [] : [$roleName],
            'permissions' => $rows
                ->pluck('permission_name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
