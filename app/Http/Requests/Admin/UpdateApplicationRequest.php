<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\DiscardsBlankUris;
use App\Concerns\ResolvesApplicationFromRoute;
use App\Rules\RedirectUri;
use App\Services\ScopeRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
{
    use DiscardsBlankUris;
    use ResolvesApplicationFromRoute;

    /**
     * Only somebody who may edit this application.
     *
     * Asked here as well as in the controller because a form request
     * validates before the action runs: without this, somebody who may not
     * edit this application would be answered with the shape of the form
     * rather than with a refusal.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->application()) === true;
    }

    /**
     * Determine whether this request may decide who reaches the application.
     *
     * Two of the fields on the edit form are not configuration: whether the
     * consent screen appears, and whether the grants list is enforced at all.
     * Both answer "who may sign in to this", which is an administrator's
     * decision — somebody who only looks after the application would otherwise
     * reach through the edit form what the access page refuses them.
     */
    public function mayDecideAccess(): bool
    {
        return $this->user()?->can('manageAccess', $this->application()) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The application type is absent on purpose: changing it would alter the
     * grant types and the presence of a secret, silently breaking every
     * integration already using the client.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usesRedirectUris = $this->application()->type()->usesRedirectUris();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'redirect_uris' => [
                'array',
                'max:'.config('sso.redirect_uris.max_per_application'),
                Rule::requiredIf($usesRedirectUris),
            ],
            'redirect_uris.*' => ['required', 'string', 'max:2000', 'distinct', new RedirectUri],
            'post_logout_redirect_uris' => ['array', 'max:20'],
            'post_logout_redirect_uris.*' => ['required', 'string', 'max:2000', 'distinct', new RedirectUri],
            'scopes' => ['array'],
            'scopes.*' => ['string', Rule::in(app(ScopeRegistry::class)->ids())],
            'skips_authorization' => ['boolean'],
            'restricts_access' => ['boolean'],
        ];
    }

    /**
     * The attributes this request is allowed to change.
     *
     * The access decisions are dropped rather than defaulted for a caller who
     * may not make them, so an omitted field leaves the application as it was.
     *
     * @return array{name: string, description: string|null, redirect_uris: array<int, string>, post_logout_redirect_uris: array<int, string>, scopes: array<int, string>, skips_authorization?: bool, restricts_access?: bool}
     */
    public function payload(): array
    {
        /** @var string $name */
        $name = $this->validated('name');
        /** @var string|null $description */
        $description = $this->validated('description');
        /** @var array<int, string> $redirectUris */
        $redirectUris = $this->validated('redirect_uris', []);
        /** @var array<int, string> $postLogoutUris */
        $postLogoutUris = $this->validated('post_logout_redirect_uris', []);
        /** @var array<int, string> $scopes */
        $scopes = $this->validated('scopes', []);

        $attributes = [
            'name' => $name,
            'description' => $description,
            'redirect_uris' => $redirectUris,
            'post_logout_redirect_uris' => $postLogoutUris,
            'scopes' => $scopes,
        ];

        if (! $this->mayDecideAccess()) {
            return $attributes;
        }

        return [
            ...$attributes,
            'skips_authorization' => $this->boolean('skips_authorization'),
            'restricts_access' => $this->boolean('restricts_access'),
        ];
    }
}
