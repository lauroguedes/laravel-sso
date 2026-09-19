<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\DescribesApplicationSections;
use App\Concerns\SortsListings;
use App\Http\Requests\Admin\ApplicationGrantRequest;
use App\Models\Application;
use App\Models\ApplicationUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages which users may sign in to an application, and the role they hold.
 *
 * Access and role are separate: a user may be granted access with no role,
 * which authenticates them without granting any capability.
 *
 * The grant, role change and revoke events are raised by the models, so every
 * caller emits them, not just this controller.
 */
class ApplicationGrantController extends Controller
{
    use DescribesApplicationSections;
    use SortsListings;

    /**
     * Show the users with access to an application.
     */
    public function index(Request $request, Application $application): Response
    {
        $this->authorize('viewAccess', $application);

        /*
         * Two listings, so each carries its own search, size and page, and each
         * is a closure: narrowing one does not run the other's queries.
         */
        $grants = $this->listing($request, 'grants_');
        $grantSearch = $grants->string('search')->toString() ?: null;

        $candidates = $this->listing($request, 'candidates_');
        $candidateSearch = $candidates->string('search')->toString() ?: null;

        return Inertia::render('applications/Access', [
            'application' => $application->toHeader(),
            'sections' => $this->applicationSections($request->user(), $application),
            'canManageApplication' => $request->user()->can('update', $application),
            'grantFilters' => $this->listingFilters($grants),
            'candidateFilters' => $this->listingFilters($candidates),
            'grants' => fn (): mixed => $this->grantsPage($application, $grantSearch, $this->perPage($grants)),
            /*
             * Only users who do not already have access, so the picker cannot
             * offer a choice that validation would then reject.
             */
            'candidates' => fn () => User::query()
                ->search($candidateSearch)
                ->whereDoesntHave('applicationGrants', fn ($query) => $query
                    ->where('application_id', $application->id))
                ->orderBy('name')
                ->orderBy('id')
                ->paginate($this->perPage($candidates), ['id', 'name', 'email'], 'candidates_page')
                ->withQueryString(),
            'roles' => $application->roles()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('manageAccess', $application),
        ]);
    }

    /**
     * The people who already have access, a page at a time.
     *
     * Sorted and paginated in SQL: the name lives on the joined user, so
     * ordering in PHP would have meant loading every grant. Returned as mixed,
     * as AuditController::entries() is, because PHPStan will not match a
     * paginator mapped through through() against any declared row type.
     */
    private function grantsPage(Application $application, ?string $search, int $perPage): mixed
    {
        return $application->grants()
            ->with('user:id,name,email,disabled_at')
            ->join('users', 'users.id', '=', 'application_user.user_id')
            ->when($search, fn ($query, string $term) => $query->whereIn(
                'application_user.user_id',
                User::query()->search($term)->select('id'),
            ))
            ->orderBy('users.name')
            ->orderBy('application_user.id')
            ->paginate($perPage, ['application_user.*'], 'grants_page')
            ->withQueryString()
            ->through(fn (ApplicationUser $grant): array => [
                'id' => $grant->id,
                'user' => [
                    'id' => $grant->user->id,
                    'name' => $grant->user->name,
                    'email' => $grant->user->email,
                    'disabled' => $grant->user->isDisabled(),
                ],
                'role_id' => $grant->application_role_id,
            ]);
    }

    /**
     * Grant a user access to the application.
     */
    public function store(ApplicationGrantRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('manageAccess', $application);

        $application->grantAccessTo(
            User::query()->findOrFail((int) $request->validated('user_id')),
            $request->applicationRole(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access granted.')]);

        return back();
    }

    /**
     * Change the role a user holds in the application.
     */
    public function update(
        ApplicationGrantRequest $request,
        Application $application,
        ApplicationUser $grant
    ): RedirectResponse {
        $this->authorize('manageAccess', $application);

        $grant->assignRole($request->applicationRole());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    /**
     * Withdraw a user's access to the application.
     */
    public function destroy(Application $application, ApplicationUser $grant): RedirectResponse
    {
        $this->authorize('manageAccess', $application);

        $grant->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access revoked.')]);

        return back();
    }
}
