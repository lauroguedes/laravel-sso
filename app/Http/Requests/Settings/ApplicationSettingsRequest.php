<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\SettingsSection;
use App\Models\Setting;
use App\Services\InterfaceOptions;
use App\Services\ScopeRegistry;
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
     * A link somebody will follow from a page this server shows.
     */
    private const WEB_ADDRESS = ['string', 'max:2000', 'url:http,https'];

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
        /*
         * Built once a request: reading it resolves two services and
         * constructs every allowlist, and one update asks for it twice — to
         * validate, and to decide which settings the section owns.
         */
        return once(fn (): array => self::build());
    }

    /**
     * @return array<string, array<string, ValidationRule|array<mixed>|string>>
     */
    private static function build(): array
    {
        $options = app(InterfaceOptions::class);
        $palette = app(ThemePalette::class);

        return [
            SettingsSection::Brand->value => [
                'brand_name' => ['required', 'string', 'max:60'],
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:512'],
                'remove_logo' => ['boolean'],
            ],
            SettingsSection::Appearance->value => [
                'base_color' => ['required', Rule::in($palette->names('base_color'))],
                'accent' => ['required', Rule::in($palette->names('accent'))],
            ],
            SettingsSection::Layout->value => [
                'rows_per_page' => ['required', Rule::in($options->values('rows_per_page'))],
                'sidebar_variant' => ['required', Rule::in($options->values('sidebar_variant'))],
                'auth_layout' => ['required', Rule::in($options->values('auth_layout'))],
                'auth_background' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                'remove_auth_background' => ['boolean'],
            ],
            SettingsSection::Links->value => [
                'documentation_links' => ['array', 'max:6'],
                'documentation_links.*.label' => ['required', 'string', 'max:40'],
                'documentation_links.*.url' => ['required', ...self::WEB_ADDRESS],
            ],
            SettingsSection::Access->value => [
                'allow_registration' => ['boolean'],
                'require_email_verification' => ['boolean'],
                'logout_other_sessions_on_password_change' => ['boolean'],
                /* Bounded where the field that offers them is bounded. */
                ...array_combine(
                    $options->durationNames(),
                    array_map($options->durationRules(...), $options->durationNames()),
                ),
            ],
            SettingsSection::Consent->value => [
                'consent_heading' => ['required', 'string', 'max:120'],
                'consent_message' => ['nullable', 'string', 'max:300'],
                'consent_scope_descriptions' => ['array'],
                'consent_scope_descriptions.*' => ['string', 'max:160'],
                'consent_show_account' => ['boolean'],
                'consent_remember_approvals' => ['boolean'],
                'consent_privacy_url' => ['nullable', ...self::WEB_ADDRESS],
                'consent_terms_url' => ['nullable', ...self::WEB_ADDRESS],
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
        $sections = self::sections();

        return [
            'section' => ['required', Rule::in(array_keys($sections))],
            ...$sections[$this->input('section')] ?? [],
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
        $this->keepWrittenScopeWording();
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
        $numbers = ['rows_per_page', ...app(InterfaceOptions::class)->durationNames()];

        foreach ($numbers as $key) {
            if ($this->has($key)) {
                $this->merge([$key => (int) $this->input($key)]);
            }
        }
    }

    /**
     * The same, for the switches of the section being saved.
     *
     * An unchecked switch posts nothing at all, so each one is read as false
     * rather than left absent: otherwise turning a switch off would save a
     * payload that says nothing about it. A switch is a setting validated as
     * nothing but a boolean; the "remove_" flags beside uploads are not
     * settings.
     */
    private function castSwitches(): void
    {
        foreach (self::sections()[$this->string('section')->toString()] ?? [] as $key => $rules) {
            if ($rules === ['boolean'] && ! str_starts_with($key, 'remove_')) {
                $this->merge([$key => $this->boolean($key)]);
            }
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

    /**
     * Keep scope wording only where somebody wrote some, for scopes that exist.
     *
     * A blank field arrives as null and means "use the standard description",
     * and wording for a scope since removed from the configuration would be
     * stored for nothing to show.
     */
    private function keepWrittenScopeWording(): void
    {
        if ($this->input('section') !== 'consent') {
            return;
        }

        $wording = $this->input('consent_scope_descriptions');

        $this->merge([
            'consent_scope_descriptions' => array_filter(
                array_intersect_key(is_array($wording) ? $wording : [], array_flip(app(ScopeRegistry::class)->ids())),
                is_string(...),
            ),
        ]);
    }
}
