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
}
