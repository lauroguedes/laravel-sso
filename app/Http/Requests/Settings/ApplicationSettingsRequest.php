<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\Setting;
use App\Services\InterfaceOptions;
use App\Services\ThemePalette;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One section of the application settings.
 *
 * The form saves a tab at a time, so a request carries the section it came
 * from and is validated against that section alone. Validating the whole set
 * every time would mean every tab had to post every field — a form that
 * quietly resubmits settings the reader never looked at, and a validation
 * error on a tab they cannot see.
 */
class ApplicationSettingsRequest extends FormRequest
{
    /**
     * What each section is allowed to contain.
     *
     * Every choice is an allowlist rather than free text: each names something
     * that has to exist — a component, a palette, a page size — and a value the
     * interface cannot render is a page that fails to load rather than a
     * setting that merely looks wrong. The lists come from whoever owns the
     * thing being named, so the form cannot offer something this refuses.
     *
     * @return array<string, array<string, ValidationRule|array<mixed>|string>>
     */
    public static function sections(): array
    {
        $options = app(InterfaceOptions::class);
        $palette = app(ThemePalette::class);

        return [
            'brand' => [
                'brand_name' => ['required', 'string', 'max:60'],
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:512'],
                'remove_logo' => ['boolean'],
            ],
            'appearance' => [
                'base_color' => ['required', Rule::in($palette->names('base_color'))],
                'accent' => ['required', Rule::in($palette->names('accent'))],
            ],
            'layout' => [
                'rows_per_page' => ['required', Rule::in($options->values('rows_per_page'))],
                'sidebar_variant' => ['required', Rule::in($options->values('sidebar_variant'))],
                'auth_layout' => ['required', Rule::in($options->values('auth_layout'))],
                'auth_background' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                'remove_auth_background' => ['boolean'],
            ],
            'links' => [
                'documentation_links' => ['array', 'max:6'],
                'documentation_links.*.label' => ['required', 'string', 'max:40'],
                'documentation_links.*.url' => ['required', 'string', 'max:2000', 'url:http,https'],
            ],
            'access' => [
                'allow_registration' => ['boolean'],
                'require_email_verification' => ['boolean'],
                /*
                 * Bounded at both ends. A one-second access token is a server
                 * nothing can integrate with, and a year-long one is a
                 * credential that outlives the reason it was issued.
                 */
                'access_token_ttl' => ['required', 'integer', 'min:60', 'max:86400'],
                'refresh_token_ttl' => ['required', 'integer', 'min:300', 'max:31536000'],
                'id_token_ttl' => ['required', 'integer', 'min:60', 'max:86400'],
                'session_lifetime' => ['required', 'integer', 'min:5', 'max:43200'],
                'logout_other_sessions_on_password_change' => ['boolean'],
                'audit_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            ],
        ];
    }

    /**
     * Only an administrator who may manage this server's settings.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Setting::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', Rule::in(array_keys(self::sections()))],
            ...self::sections()[$this->input('section')] ?? [],
        ];
    }

    /**
     * The settings this request is asking to change.
     *
     * Named after the section rather than taken from the payload, so a request
     * that smuggles in a field belonging to another tab changes nothing.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $keys = array_filter(
            array_keys(self::sections()[$this->validated('section')]),
            /*
             * The uploads are excluded by shape rather than by name: they are
             * a file field and its companion "remove" flag, neither of which
             * is the setting itself. The controller resolves them into one
             * stored path, because only it knows what the previous file was.
             */
            fn (string $key): bool => ! $this->hasFile($key) && ! str_starts_with($key, 'remove_'),
        );

        return array_intersect_key($this->safe()->all(), array_flip($keys));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->castNumbers();
        $this->castSwitches();
        $this->dropBlankLinks();
    }

    /**
     * Turn the strings a form posts into the numbers the settings hold.
     *
     * They are compared strictly, both against the values on offer and against
     * the default that decides whether a row is stored at all, so "25" would
     * validate and then silently do nothing.
     */
    private function castNumbers(): void
    {
        foreach (['rows_per_page', 'access_token_ttl', 'refresh_token_ttl', 'id_token_ttl', 'session_lifetime', 'audit_retention_days'] as $key) {
            if ($this->has($key)) {
                $this->merge([$key => (int) $this->input($key)]);
            }
        }
    }

    /**
     * The same, for the switches.
     *
     * An unchecked switch posts nothing at all, so each one is read as false
     * rather than left absent — otherwise turning registration off would save
     * a payload that says nothing about registration.
     */
    private function castSwitches(): void
    {
        if ($this->input('section') !== 'access') {
            return;
        }

        foreach (['allow_registration', 'require_email_verification', 'logout_other_sessions_on_password_change'] as $key) {
            $this->merge([$key => $this->boolean($key)]);
        }
    }

    /**
     * Drop the link rows nobody filled in.
     *
     * They arrive from a repeating field, so an untouched pair is two empty
     * strings rather than an absent entry. Dropping those keeps "every stored
     * link is a real link" true without loosening the rules.
     */
    private function dropBlankLinks(): void
    {
        if ($this->input('section') !== 'links') {
            return;
        }

        /*
         * Removing the last link posts no rows at all, and an absent key would
         * be read as "this section had nothing to say" rather than "there are
         * no links now".
         */
        $links = $this->input('documentation_links');
        $links = is_array($links) ? $links : [];

        $this->merge([
            'documentation_links' => array_values(array_filter(
                $links,
                fn (mixed $link): bool => is_array($link)
                    && trim((string) ($link['label'] ?? '')) !== ''
                    && trim((string) ($link['url'] ?? '')) !== '',
            )),
        ]);
    }
}
