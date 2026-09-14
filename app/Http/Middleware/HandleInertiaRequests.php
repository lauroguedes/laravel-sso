<?php

namespace App\Http\Middleware;

use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\AuditRecord;
use App\Models\User;
use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function __construct(private readonly Settings $settings) {}

    /**
     * Handle the incoming request.
     *
     * A visit the page makes itself, such as signing in or answering the
     * consent screen, can end in a redirect to an application on another
     * origin: its redirect URI, carrying a code or an error. The page's request
     * would follow that redirect and be refused by the browser, leaving the
     * user where they were, so the page is told to navigate there instead.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);
        $location = $response->headers->get('Location');

        if ($request->header('X-Inertia') && $response->isRedirection() && is_string($location) && $this->leavesThisServer($location, $request)) {
            return Inertia::location($location);
        }

        return $response;
    }

    /**
     * Whether a redirect points at another origin.
     */
    private function leavesThisServer(string $location, Request $request): bool
    {
        $origin = $request->getSchemeAndHttpHost();

        return parse_url($location, PHP_URL_HOST) !== null
            && $location !== $origin
            && ! str_starts_with($location, $origin.'/');
    }

    /**
     * How this server presents itself, chosen by an administrator.
     *
     * Shared on every page because the brand appears in the shell and the
     * layout choice decides which shell renders at all — including on the
     * sign-in page, before anybody is authenticated.
     *
     * @return array<string, mixed>
     */
    private function branding(): array
    {
        $settings = $this->settings->all();
        $disk = Storage::disk('public');
        $url = fn (string $key): ?string => ($path = $this->settings->path($key)) === null
            ? null
            : $disk->url($path);

        return [
            'name' => $settings['brand_name'],
            'logo' => $url('brand_logo'),
            'sidebarVariant' => $settings['sidebar_variant'],
            'authLayout' => $settings['auth_layout'],
            'authBackground' => $url('auth_background'),
            'documentationLinks' => $settings['documentation_links'],
        ];
    }

    /**
     * The sections of the interface this user may open.
     *
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return [
            'viewApplications' => $user->can('viewAny', Application::class),
            'viewUsers' => $user->can('viewAny', User::class),
            'viewAudit' => $user->can('viewAudit', AuditRecord::class),
            'manageSettings' => $user->can(PlatformPermission::SettingsManage->value),
        ];
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'branding' => $this->branding(),
            'auth' => [
                'user' => $request->user(),
            ],
            /*
             * The discovery URL is derived from the issuer rather than from
             * route(), because the issuer is the canonical public URL and may
             * differ from APP_URL behind a proxy. Sharing it keeps every page
             * showing the URL a relying party would actually fetch.
             */
            'sso' => [
                'discoveryUrl' => config('sso.discovery_url'),
            ],
            /*
             * What this user may reach, decided by the same policies that
             * guard the routes. The sidebar renders from this rather than
             * showing every section and letting the click fail: a menu that
             * offers a page the reader cannot open is a menu that lies.
             */
            'can' => $this->abilities($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
