<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        return Inertia::render('applications/Index', [
            'filters' => ['search' => $request->string('search')->toString() ?: null],
            'applications' => Application::query()
                ->search($request->string('search')->toString() ?: null)
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Application $application): array => $this->summarize($application)),
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
            'availableScopes' => $this->availableScopes(),
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
            'scopes' => $request->validated('scopes', []),
            'skips_authorization' => $request->boolean('skips_authorization'),
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
            'issuer' => config('sso.issuer'),
            'discoveryUrl' => rtrim((string) config('sso.issuer'), '/').'/.well-known/openid-configuration',
            /*
             * Present only on the request immediately after the secret was
             * generated or rotated.
             */
            'clientSecret' => $request->session()->get('clientSecret'),
            'canManage' => $request->user()->can('update', $application),
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
            'availableScopes' => $this->availableScopes(),
        ]);
    }

    /**
     * Update an application.
     */
    public function update(UpdateApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $this->applications->update($application, [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'redirect_uris' => $request->validated('redirect_uris', []),
            'scopes' => $request->validated('scopes', []),
            'skips_authorization' => $request->boolean('skips_authorization'),
        ]);

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
            'id' => $application->id,
            'name' => $application->name,
            'description' => $application->description,
            'type' => $this->applications->typeOf($application)->value,
            'type_label' => $this->applications->typeOf($application)->label(),
            'enabled' => $application->isEnabled(),
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
            'redirect_uris' => $application->redirect_uris ?? [],
            'scopes' => $application->scopes ?? [],
            'grant_types' => $application->grant_types ?? [],
            'skips_authorization' => (bool) $application->skips_authorization,
            'updated_at' => $application->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The kinds of application that may be registered.
     *
     * @return array<int, array<string, mixed>>
     */
    private function applicationTypes(): array
    {
        return array_map(fn (ApplicationType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'confidential' => $type->isConfidential(),
            'uses_redirect_uris' => $type->usesRedirectUris(),
        ], ApplicationType::cases());
    }

    /**
     * The scopes an application may be granted.
     *
     * @return array<int, array{id: string, description: string}>
     */
    private function availableScopes(): array
    {
        return $this->scopes->all();
    }
}
