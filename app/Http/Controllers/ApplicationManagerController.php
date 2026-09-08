<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\DescribesApplicationSections;
use App\Enums\PlatformPermission;
use App\Http\Requests\Admin\ApplicationManagerRequest;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who looks after one application.
 *
 * Separate from the access grants beside it: that decides who may sign in,
 * this decides who may change what the application is. Assigning somebody
 * here is what turns the Developer role from a label into reach, and it is
 * always an administrator's decision — a steward extending their own would
 * make the assignment self-serving.
 */
class ApplicationManagerController extends Controller
{
    use DescribesApplicationSections;

    /**
     * Show the people who look after an application.
     */
    public function index(Request $request, Application $application): Response
    {
        $this->authorize('manageStewards', $application);

        $search = $request->string('search')->toString() ?: null;

        return Inertia::render('applications/Managers', [
            'application' => $application->toHeader(),
            'sections' => $this->applicationSections($request->user(), $application),
            'canManageApplication' => $request->user()->can('update', $application),
            'filters' => ['search' => $search],
            'managers' => $application->managers()
                ->orderBy('name')
                ->get(['users.id', 'name', 'email', 'disabled_at'])
                ->map(fn (User $manager): array => [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'email' => $manager->email,
                    'disabled' => $manager->isDisabled(),
                ])
                ->all(),
            /*
             * People the assignment would actually change something for: they
             * hold the Developer permission, do not already look after this
             * application, and are not administrators — an administrator
             * already reaches every application, so assigning one would record
             * nothing their permission does not already say.
             */
            'candidates' => User::query()
                ->permission(PlatformPermission::ApplicationsDevelop->value)
                ->withoutPermission(PlatformPermission::ApplicationsManage->value)
                ->withStatus('active')
                ->search($search)
                ->whereDoesntHave('managedApplications', fn ($query) => $query
                    ->whereKey($application->getKey()))
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Put somebody in charge of the application.
     */
    public function store(ApplicationManagerRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('manageStewards', $application);

        $application->managers()->syncWithoutDetaching([$request->validated('user_id')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Manager added.')]);

        return back();
    }

    /**
     * Take them off it.
     */
    public function destroy(Application $application, User $manager): RedirectResponse
    {
        $this->authorize('manageStewards', $application);

        $application->managers()->detach($manager->getKey());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Manager removed.')]);

        return back();
    }
}
