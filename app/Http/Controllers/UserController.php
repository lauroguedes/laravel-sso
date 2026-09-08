<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\SortsListings;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\ApplicationUser;
use App\Models\User;
use App\Services\UserManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use SortsListings;

    public function __construct(private readonly UserManager $users) {}

    /**
     * List the users of this Identity Provider.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $listing = User::query()
            /*
             * summarize() reads the role names, which spatie resolves per
             * model. Without this the listing issues one extra query per row.
             */
            ->with('roles:id,name')
            ->search($request->string('search')->toString() ?: null);

        return Inertia::render('users/Index', [
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                ...$this->sortFilters($request, User::sortableColumns()),
            ],
            /*
             * Creating and editing a user are the same permission, and
             * UserPolicy::update() does not look at the target, so one
             * page-level answer is accurate for every row's actions.
             */
            'canManage' => $request->user()->can('create', User::class),
            'users' => $this->applySort($listing, $request, User::sortableColumns())
                /*
                 * A stable tiebreaker, so that rows with equal values keep the
                 * same order between pages rather than drifting.
                 */
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (User $user): array => $this->summarize($user)),
        ]);
    }

    /**
     * Show the form for registering a user.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/Create', [
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    /**
     * Register a user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->users->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'email_verified' => $request->boolean('email_verified'),
            'roles' => $request->validated('roles', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.edit', $user);
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('users/Edit', [
            'user' => $this->summarize($user->load('roles:id,name')),
            'applicationAccess' => $this->applicationAccess($user),
            'availableRoles' => $this->availableRoles(),
            'canManage' => $request->user()->can('update', $user),
            'canChangeStatus' => $request->user()->can('updateStatus', $user),
            'canRevokeSessions' => $request->user()->can('revokeSessions', User::class),
        ]);
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->users->update($user, [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'email_verified' => $request->boolean('email_verified'),
            'roles' => $request->validated('roles', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.edit', $user);
    }

    /**
     * The shape of a user shown in the administration interface.
     *
     * @return array<string, mixed>
     */
    private function summarize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'disabled' => $user->isDisabled(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->getRoleNames()->all(),
        ];
    }

    /**
     * The applications this user may sign in to, and the role held in each.
     *
     * Read only here: access is granted from the application's own page,
     * which is where the roles it defines are listed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function applicationAccess(User $user): array
    {
        return $user->applicationGrants()
            ->with(['application:id,name,revoked', 'role:id,name'])
            ->get()
            ->sortBy(fn (ApplicationUser $grant): string => $grant->application->name)
            ->values()
            ->map(fn (ApplicationUser $grant): array => [
                'application_id' => $grant->application_id,
                'application_name' => $grant->application->name,
                'application_enabled' => $grant->application->isEnabled(),
                'role_name' => $grant->role?->name,
            ])
            ->all();
    }

    /**
     * The platform roles that may be assigned to a user.
     *
     * @return array<int, string>
     */
    private function availableRoles(): array
    {
        return Role::query()->orderBy('name')->pluck('name')->all();
    }
}
