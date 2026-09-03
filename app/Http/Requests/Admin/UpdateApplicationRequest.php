<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Application;
use App\Rules\RedirectUri;
use App\Services\ScopeRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
{
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
            'scopes' => ['array'],
            'scopes.*' => ['string', Rule::in(app(ScopeRegistry::class)->ids())],
            'skips_authorization' => ['boolean'],
        ];
    }

    /**
     * The application being updated, resolved from the route binding.
     */
    public function application(): Application
    {
        $application = $this->route('application');

        return $application instanceof Application
            ? $application
            : Application::findOrFail((string) $application);
    }
}
