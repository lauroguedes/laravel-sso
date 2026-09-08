<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingsSection;

/**
 * The choices the App settings form offers.
 *
 * One owner, because each choice is read twice: the page renders it, and the
 * form request validates against it. Spelled out in both places they drift,
 * and the drift is silent in the worse direction — the page offers something
 * the request refuses, or the request accepts a value nothing can render.
 *
 * Colours are next door in ThemePalette, which owns their definitions as well
 * as their names.
 */
class InterfaceOptions
{
    /**
     * The page sizes a listing offers. The first is the default.
     *
     * SortsListings::perPage() compares strictly against this, so a size the
     * form allowed but the trait did not would validate and then silently
     * fall back to the first.
     *
     * @var list<int>
     */
    public const PAGE_SIZES = [15, 25, 50, 100];

    /**
     * How the administration shell is laid out.
     *
     * @var list<array{value: string, label: string, description: string}>
     */
    private const SIDEBAR_VARIANTS = [
        ['value' => 'inset', 'label' => 'Inset', 'description' => 'The content sits in a rounded panel beside the menu.'],
        ['value' => 'sidebar', 'label' => 'Flush', 'description' => 'The menu meets the content edge to edge.'],
        ['value' => 'floating', 'label' => 'Floating', 'description' => 'The menu floats as a card over the background.'],
    ];

    /**
     * How the sign-in page is laid out.
     *
     * @var list<array{value: string, label: string, description: string}>
     */
    private const AUTH_LAYOUTS = [
        ['value' => 'simple', 'label' => 'Simple', 'description' => 'The form centred on the page.'],
        ['value' => 'card', 'label' => 'Card', 'description' => 'The form in a card on a muted background.'],
        ['value' => 'split', 'label' => 'Split', 'description' => 'The form beside a panel carrying the brand.'],
    ];

    /**
     * The lengths of time an administrator sets, and what each is counted in.
     *
     * The unit is the server's fact: it is decided by whatever consumer reads
     * the setting — the issuer counts token lifetimes in seconds, the session
     * guard counts minutes, the audit clean-up counts days. Bounds live here
     * with it, so the field's own affordance and the rule that rejects the
     * value cannot disagree.
     *
     * @var array<string, array{unit: string, min: int, max: int}>
     */
    private const DURATIONS = [
        /*
         * Bounded at both ends. A one-second access token is a server nothing
         * can integrate with, and a year-long one is a credential that
         * outlives the reason it was issued.
         */
        'access_token_ttl' => ['unit' => 'seconds', 'min' => 60, 'max' => 86400],
        'refresh_token_ttl' => ['unit' => 'seconds', 'min' => 300, 'max' => 31536000],
        'id_token_ttl' => ['unit' => 'seconds', 'min' => 60, 'max' => 86400],
        'session_lifetime' => ['unit' => 'minutes', 'min' => 5, 'max' => 43200],
        'audit_retention_days' => ['unit' => 'days', 'min' => 7, 'max' => 3650],
    ];

    /**
     * Everything the form offers, ready to render.
     *
     * @return array<string, list<mixed>>
     */
    public function all(ThemePalette $palette): array
    {
        return [
            'baseColors' => $palette->baseColors(),
            'accents' => $palette->accents(),
            'rowsPerPage' => $this->values('rows_per_page'),
            'sidebarVariants' => self::SIDEBAR_VARIANTS,
            'authLayouts' => self::AUTH_LAYOUTS,
            'durations' => $this->durations(),
            'sections' => SettingsSection::options(),
        ];
    }

    /**
     * The duration settings, named so the page can render each one's field.
     *
     * @return list<array{name: string, unit: string, min: int, max: int}>
     */
    public function durations(): array
    {
        return array_map(
            fn (string $name): array => ['name' => $name, ...self::DURATIONS[$name]],
            array_keys(self::DURATIONS),
        );
    }

    /**
     * The rules one duration is bounded by.
     *
     * @return list<string>
     */
    public function durationRules(string $setting): array
    {
        return [
            'required',
            'integer',
            'min:'.self::DURATIONS[$setting]['min'],
            'max:'.self::DURATIONS[$setting]['max'],
        ];
    }

    /**
     * The settings that hold a length of time.
     *
     * @return list<string>
     */
    public function durationNames(): array
    {
        return array_keys(self::DURATIONS);
    }

    /**
     * The values a request may use for one setting.
     *
     * @return list<int|string>
     */
    public function values(string $setting): array
    {
        return match ($setting) {
            'rows_per_page' => self::PAGE_SIZES,
            'sidebar_variant' => array_column(self::SIDEBAR_VARIANTS, 'value'),
            'auth_layout' => array_column(self::AUTH_LAYOUTS, 'value'),
            default => [],
        };
    }
}
