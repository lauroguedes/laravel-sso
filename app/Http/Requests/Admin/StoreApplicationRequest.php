<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ApplicationType;
use App\Rules\RedirectUri;
use App\Services\ScopeRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(ApplicationType::class)],
            'redirect_uris' => [
                'array',
                'max:'.config('sso.redirect_uris.max_per_application'),
                Rule::requiredIf(fn (): bool => $this->applicationType()?->usesRedirectUris() === true),
            ],
            'redirect_uris.*' => ['required', 'string', 'max:2000', new RedirectUri],
            'scopes' => ['array'],
            'scopes.*' => ['string', Rule::in(app(ScopeRegistry::class)->ids())],
            'skips_authorization' => ['boolean'],
        ];
    }

    /**
     * Reject duplicate redirect URIs, which are matched exactly and so would
     * be meaningless repeated.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                /** @var array<int, string> $uris */
                $uris = $this->input('redirect_uris', []);

                if (count($uris) !== count(array_unique($uris))) {
                    $validator->errors()->add('redirect_uris', 'Redirect URIs must be unique.');
                }
            },
        ];
    }

    /**
     * The submitted application type, when it is a known value.
     */
    public function applicationType(): ?ApplicationType
    {
        return ApplicationType::tryFrom((string) $this->input('type'));
    }
}
