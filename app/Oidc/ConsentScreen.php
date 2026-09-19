<?php

declare(strict_types=1);

namespace App\Oidc;

use App\Services\ScopeRegistry;
use App\Services\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * The consent page, worded the way an administrator chose on the Consent tab.
 *
 * Rendered through Inertia rather than a Blade view, so it is drawn with the
 * same components as the rest of the interface.
 */
class ConsentScreen
{
    /**
     * What the page says under its heading when neither the administrator nor
     * the application wrote anything.
     */
    public const DEFAULT_MESSAGE = 'This application is asking to use your account.';

    public function __construct(
        private readonly Settings $settings,
        private readonly ScopeRegistry $scopes,
    ) {}

    /**
     * Render the page Passport asks for when an application needs approval.
     *
     * @param  array<int, Scope>  $scopes
     */
    public function render(Client $client, array $scopes, string $authToken, Request $request): Response
    {
        return Inertia::render('oauth/Authorize', [
            'application' => [
                'name' => $client->name,
                'description' => $client->getAttribute('description'),
            ],
            'scopes' => $this->describe($scopes),
            'authToken' => $authToken,
            'consent' => $this->wording($client, $request),
        ])->toResponse($request);
    }

    /**
     * Each scope as the page words it: the administrator's wording, or else the
     * description the scope has in configuration.
     *
     * @param  array<int, Scope>  $scopes
     * @return list<array{id: string, description: mixed}>
     */
    private function describe(array $scopes): array
    {
        $wording = (array) $this->settings->get('consent_scope_descriptions');
        $standard = array_column($this->scopes->all(), 'description', 'id');

        return array_values(array_map(fn (Scope $scope): array => [
            'id' => $scope->id,
            'description' => $wording[$scope->id] ?? $standard[$scope->id] ?? $scope->description,
        ], $scopes));
    }

    /**
     * What the page says, with the asking application named.
     *
     * "Use another account" goes back through this same authorization with
     * prompt=login, which Passport answers by signing the user out and in.
     *
     * @return array{heading: string, message: string, switchAccountUrl: string|null, privacyUrl: mixed, termsUrl: mixed}
     */
    private function wording(Client $client, Request $request): array
    {
        $named = fn (mixed $text): ?string => is_string($text) ? str_replace('{application}', $client->name, $text) : null;
        $description = $client->getAttribute('description');

        return [
            'heading' => (string) $named($this->settings->get('consent_heading')),
            'message' => $named($this->settings->get('consent_message'))
                ?? (is_string($description) && $description !== '' ? $description : self::DEFAULT_MESSAGE),
            'switchAccountUrl' => $this->settings->get('consent_show_account') === true
                ? $request->fullUrlWithQuery(['prompt' => 'login'])
                : null,
            'privacyUrl' => $this->settings->get('consent_privacy_url'),
            'termsUrl' => $this->settings->get('consent_terms_url'),
        ];
    }
}
