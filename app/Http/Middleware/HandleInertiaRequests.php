<?php

namespace App\Http\Middleware;

use App\Enums\PlatformPermission;
use App\Models\Application;
use App\Models\AuditRecord;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

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
