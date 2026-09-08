<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\DescribesApplicationSections;
use App\Concerns\SortsListings;
use App\Enums\ApplicationType;
use App\Http\Requests\Admin\StoreApplicationRequest;
use App\Http\Requests\Admin\UpdateApplicationRequest;
use App\Models\Application;
use App\Services\ApplicationManager;
use App\Services\ScopeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    use DescribesApplicationSections;
    use SortsListings;

    public function __construct(
        private readonly ApplicationManager $applications,
        private readonly ScopeRegistry $scopes,
    ) {}

    /**
     * List the applications registered on this server.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Application::class);

        $status = $request->string('status')->toString() ?: null;
        $type = $request->string('type')->toString() ?: null;

        $listing = Application::query()
            /*
             * Scoped before anything else: a steward sees the applications
             * assigned to them, and the count under the table has to be of
             * those rather than of every application on the server.
             */
            ->visibleTo($request->user())
            ->search($request->string('search')->toString() ?: null)
            ->withStatus($status)
            ->ofType($type);

        return Inertia::render('applications/Index', [
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $status,
                'type' => $type,
                ...$this->sortFilters($request, Application::sortableColumns()),
            ],
            'applicationTypes' => array_map(fn (ApplicationType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], ApplicationType::cases()),
            /*
             * Registering an application, revoking its tokens and taking it
             * out of service all need the same platform permission, so one
             * page-level answer serves all three. Editing does not: a steward
             * may edit the applications assigned to them and register none, so
             * each row carries its own answer.
             */
            'canAdminister' => $request->user()->can('create', Application::class),
            'applications' => $this->applySort($listing, $request, Application::sortableColumns())
                /*
                 * A stable tiebreaker, so equal values keep the same order
                 * between pages rather than drifting.
                 */
                ->orderBy('name')
                ->paginate($this->perPage($request))
                ->withQueryString()
                ->through(fn (Application $application): array => [
                    ...$this->summarize($application),
                    'can_manage' => $request->user()->can('update', $application),
                ]),
        ]);
    }

    /**
     * Show the form for registering an application.
     */
    public function create(): Response
    {
        $this->authorize('create', Application::class);

        return Inertia::render('applications/Create', [
            'applicationTypes' => $this->applicationTypes(),
            'availableScopes' => $this->scopes->all(),
        ]);
    }

    /**
     * Register an application.
     *
     * The client secret is handed to the next request through the session so
     * it can be shown exactly once. It is stored only as a hash and cannot be
     * recovered afterwards.
     */
    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        $application = $this->applications->create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'type' => $request->applicationType(),
            'redirect_uris' => $request->validated('redirect_uris', []),
            'post_logout_redirect_uris' => $request->validated('post_logout_redirect_uris', []),
            'scopes' => $request->validated('scopes', []),
        ]);

        return to_route('applications.show', $application)
            ->with('clientSecret', $application->plainSecret);
    }

    /**
     * Show an application and the values needed to integrate with it.
     */
    public function show(Request $request, Application $application): Response
    {
        $this->authorize('view', $application);

        return Inertia::render('applications/Show', [
            'application' => $this->detail($application),
            'sections' => $this->applicationSections($request->user(), $application),
            'issuer' => config('sso.issuer'),
            /*
             * Present only on the request immediately after the secret was
             * generated or rotated.
             */
            'clientSecret' => $request->session()->get('clientSecret'),
            'canManage' => $request->user()->can('update', $application),
            'canChangeStatus' => $request->user()->can('changeStatus', $application),
            'canRegenerateSecret' => $request->user()->can('regenerateSecret', $application),
        ]);
    }

    /**
     * Show the form for editing an application.
     */
    public function edit(Application $application): Response
    {
        $this->authorize('update', $application);

        return Inertia::render('applications/Edit', [
            'application' => $this->detail($application),
            'availableScopes' => $this->scopes->all(),
        ]);
    }

    /**
     * Update an application.
     */
    public function update(UpdateApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $this->applications->update($application, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application updated.')]);

        return to_route('applications.show', $application);
    }

    /**
     * The shape of an application shown in a list.
     *
     * The secret is never included in any of these payloads.
     *
     * @return array<string, mixed>
     */
    private function summarize(Application $application): array
    {
        return [
            ...$application->toHeader(),
            'created_at' => $application->created_at?->toIso8601String(),
        ];
    }

    /**
     * The full shape of an application, for the detail and edit screens.
     *
     * @return array<string, mixed>
     */
    private function detail(Application $application): array
    {
        return [
            ...$this->summarize($application),
            'confidential' => $application->isConfidential(),
            'uses_redirect_uris' => $application->type()->usesRedirectUris(),
            'redirect_uris' => $application->redirect_uris ?? [],
            'post_logout_redirect_uris' => $application->post_logout_redirect_uris ?? [],
            'scopes' => $application->scopes ?? [],
            'skips_authorization' => $application->skips_authorization,
            'restricts_access' => $application->restricts_access,
        ];
    }

    /**
     * The kinds of application that may be registered.
     *
     * @return array<int, array<string, mixed>>
     */
    private function applicationTypes(): array
    {
        $offered = $this->scopes->ids();

        return array_map(fn (ApplicationType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'uses_redirect_uris' => $type->usesRedirectUris(),
            'default_scopes' => $type->defaultScopes($offered),
        ], ApplicationType::cases());
    }
}
