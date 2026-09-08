<?php

declare(strict_types=1);

namespace App\Services;

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
        ];
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
