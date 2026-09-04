<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\ApplicationUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List the users of this Identity Provider.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('users/Index', [
            'filters' => ['search' => $request->string('search')->toString() ?: null],
            'users' => User::query()
                /*
                 * summarize() reads the role names, which spatie resolves per
                 * model. Without this the listing issues one extra query per
                 * row.
                 */
                ->with('roles:id,name')
                ->search($request->string('search')->toString() ?: null)
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

        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
            ]);

            if ($request->boolean('email_verified')) {
                $user->forceFill(['email_verified_at' => $user->freshTimestamp()])->save();
            }

            $user->syncRoles($request->validated('roles', []));

            return $user;
        });

        UserCreated::dispatch($user);

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
        ]);
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        DB::transaction(function () use ($request, $user): void {
            $user->fill([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
            ]);

            /*
             * Changing an address invalidates the previous verification, but
             * an administrator may mark the new address as already verified.
             */
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($request->boolean('email_verified')) {
                $user->email_verified_at ??= $user->freshTimestamp();
            } else {
                $user->email_verified_at = null;
            }

            if ($password = $request->validated('password')) {
                $user->password = $password;
            }

            $user->save();

            $user->syncRoles($request->validated('roles', []));
        });

        UserUpdated::dispatch($user);

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
