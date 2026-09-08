<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The colours an administrator chose, as CSS.
 *
 * shadcn theming is CSS custom properties and nothing else: every component
 * reads "--primary", "--background" and their neighbours, so a palette is a
 * set of overrides rather than a rebuild. That is what lets this be a runtime
 * setting on a self-hosted server — the alternative, a theme preset resolved
 * against shadcn's registry, is scaffolding that runs at build time and would
 * put an outbound network call between an administrator and their own colours.
 *
 * A base colour decides the greys the interface is mostly made of; an accent
 * decides the one colour it draws attention with. They are separate because
 * changing the accent should not repaint the whole page.
 */
class ThemePalette
{
    /**
     * Stylesheets already built, keyed by the pair that produced them.
     *
     * The tables below are constants, so a palette's CSS is a pure function of
     * two names and rebuilding it on every page render is wasted work.
     *
     * @var array<string, string>
     */
    private array $rendered = [];

    /**
     * The greys, per base colour.
     *
     * Values are Tailwind's own scales, in the HSL triplets the stylesheet
     * already uses. Only the tokens a base colour actually changes are listed;
     * everything else stays as "resources/css/app.css" defines it.
     *
     * @var array<string, array{label: string, swatch: string, light: array<string, string>, dark: array<string, string>}>
     */
    private const BASE_COLORS = [
        'neutral' => [
            'label' => 'Neutral',
            'swatch' => '0 0% 45.1%',
            'light' => [],
            'dark' => [],
        ],
        'zinc' => [
            'label' => 'Zinc',
            'swatch' => '240 3.8% 46.1%',
            'light' => [
                'background' => '0 0% 100%',
                'foreground' => '240 10% 3.9%',
                'card' => '0 0% 100%',
                'card-foreground' => '240 10% 3.9%',
                'popover' => '0 0% 100%',
                'popover-foreground' => '240 10% 3.9%',
                'primary' => '240 5.9% 10%',
                'primary-foreground' => '0 0% 98%',
                'secondary' => '240 4.8% 95.9%',
                'secondary-foreground' => '240 5.9% 10%',
                'muted' => '240 4.8% 95.9%',
                'muted-foreground' => '240 3.8% 46.1%',
                'accent' => '240 4.8% 95.9%',
                'accent-foreground' => '240 5.9% 10%',
                'border' => '240 5.9% 90%',
                'input' => '240 5.9% 90%',
                'ring' => '240 10% 3.9%',
                'sidebar-background' => '240 4.8% 97.9%',
                'sidebar-foreground' => '240 5.3% 26.1%',
                'sidebar-accent' => '240 4.8% 94%',
                'sidebar-accent-foreground' => '240 5.9% 30%',
                'sidebar-border' => '240 5.9% 91%',
            ],
            'dark' => [
                'background' => '240 10% 3.9%',
                'foreground' => '0 0% 98%',
                'card' => '240 10% 3.9%',
                'card-foreground' => '0 0% 98%',
                'popover' => '240 10% 3.9%',
                'popover-foreground' => '0 0% 98%',
                'primary' => '0 0% 98%',
                'primary-foreground' => '240 5.9% 10%',
                'secondary' => '240 3.7% 15.9%',
                'secondary-foreground' => '0 0% 98%',
                'muted' => '240 3.7% 15.9%',
                'muted-foreground' => '240 5% 64.9%',
                'accent' => '240 3.7% 15.9%',
                'accent-foreground' => '0 0% 98%',
                'border' => '240 3.7% 15.9%',
                'input' => '240 3.7% 15.9%',
                'ring' => '240 4.9% 83.9%',
                'sidebar-background' => '240 10% 7%',
                'sidebar-accent' => '240 3.7% 15.9%',
                'sidebar-accent-foreground' => '240 4.8% 95.9%',
                'sidebar-border' => '240 3.7% 15.9%',
            ],
        ],
        'slate' => [
            'label' => 'Slate',
            'swatch' => '215.4 16.3% 46.9%',
            'light' => [
                'background' => '0 0% 100%',
                'foreground' => '222.2 84% 4.9%',
                'card' => '0 0% 100%',
                'card-foreground' => '222.2 84% 4.9%',
                'popover' => '0 0% 100%',
                'popover-foreground' => '222.2 84% 4.9%',
                'primary' => '222.2 47.4% 11.2%',
                'primary-foreground' => '210 40% 98%',
                'secondary' => '210 40% 96.1%',
                'secondary-foreground' => '222.2 47.4% 11.2%',
                'muted' => '210 40% 96.1%',
                'muted-foreground' => '215.4 16.3% 46.9%',
                'accent' => '210 40% 96.1%',
                'accent-foreground' => '222.2 47.4% 11.2%',
                'border' => '214.3 31.8% 91.4%',
                'input' => '214.3 31.8% 91.4%',
                'ring' => '222.2 84% 4.9%',
                'sidebar-background' => '210 40% 98%',
                'sidebar-foreground' => '215.4 16.3% 36.9%',
                'sidebar-accent' => '210 40% 94.1%',
                'sidebar-accent-foreground' => '222.2 47.4% 21.2%',
                'sidebar-border' => '214.3 31.8% 91.4%',
            ],
            'dark' => [
                'background' => '222.2 84% 4.9%',
                'foreground' => '210 40% 98%',
                'card' => '222.2 84% 4.9%',
                'card-foreground' => '210 40% 98%',
                'popover' => '222.2 84% 4.9%',
                'popover-foreground' => '210 40% 98%',
                'primary' => '210 40% 98%',
                'primary-foreground' => '222.2 47.4% 11.2%',
                'secondary' => '217.2 32.6% 17.5%',
                'secondary-foreground' => '210 40% 98%',
                'muted' => '217.2 32.6% 17.5%',
                'muted-foreground' => '215 20.2% 65.1%',
                'accent' => '217.2 32.6% 17.5%',
                'accent-foreground' => '210 40% 98%',
                'border' => '217.2 32.6% 17.5%',
                'input' => '217.2 32.6% 17.5%',
                'ring' => '212.7 26.8% 83.9%',
                'sidebar-background' => '222.2 84% 7.9%',
                'sidebar-accent' => '217.2 32.6% 17.5%',
                'sidebar-accent-foreground' => '210 40% 98%',
                'sidebar-border' => '217.2 32.6% 17.5%',
            ],
        ],
        'stone' => [
            'label' => 'Stone',
            'swatch' => '25 5.3% 44.7%',
            'light' => [
                'background' => '0 0% 100%',
                'foreground' => '20 14.3% 4.1%',
                'card' => '0 0% 100%',
                'card-foreground' => '20 14.3% 4.1%',
                'popover' => '0 0% 100%',
                'popover-foreground' => '20 14.3% 4.1%',
                'primary' => '24 9.8% 10%',
                'primary-foreground' => '60 9.1% 97.8%',
                'secondary' => '60 4.8% 95.9%',
                'secondary-foreground' => '24 9.8% 10%',
                'muted' => '60 4.8% 95.9%',
                'muted-foreground' => '25 5.3% 44.7%',
                'accent' => '60 4.8% 95.9%',
                'accent-foreground' => '24 9.8% 10%',
                'border' => '20 5.9% 90%',
                'input' => '20 5.9% 90%',
                'ring' => '20 14.3% 4.1%',
                'sidebar-background' => '60 4.8% 97.9%',
                'sidebar-foreground' => '25 5.3% 34.7%',
                'sidebar-accent' => '60 4.8% 93.9%',
                'sidebar-accent-foreground' => '24 9.8% 30%',
                'sidebar-border' => '20 5.9% 91%',
            ],
            'dark' => [
                'background' => '20 14.3% 4.1%',
                'foreground' => '60 9.1% 97.8%',
                'card' => '20 14.3% 4.1%',
                'card-foreground' => '60 9.1% 97.8%',
                'popover' => '20 14.3% 4.1%',
                'popover-foreground' => '60 9.1% 97.8%',
                'primary' => '60 9.1% 97.8%',
                'primary-foreground' => '24 9.8% 10%',
                'secondary' => '12 6.5% 15.1%',
                'secondary-foreground' => '60 9.1% 97.8%',
                'muted' => '12 6.5% 15.1%',
                'muted-foreground' => '24 5.4% 63.9%',
                'accent' => '12 6.5% 15.1%',
                'accent-foreground' => '60 9.1% 97.8%',
                'border' => '12 6.5% 15.1%',
                'input' => '12 6.5% 15.1%',
                'ring' => '24 5.7% 82.9%',
                'sidebar-background' => '20 14.3% 7.1%',
                'sidebar-accent' => '12 6.5% 15.1%',
                'sidebar-accent-foreground' => '60 9.1% 97.8%',
                'sidebar-border' => '12 6.5% 15.1%',
            ],
        ],
        'gray' => [
            'label' => 'Gray',
            'swatch' => '220 8.9% 46.1%',
            'light' => [
                'background' => '0 0% 100%',
                'foreground' => '224 71.4% 4.1%',
                'card' => '0 0% 100%',
                'card-foreground' => '224 71.4% 4.1%',
                'popover' => '0 0% 100%',
                'popover-foreground' => '224 71.4% 4.1%',
                'primary' => '220.9 39.3% 11%',
                'primary-foreground' => '210 20% 98%',
                'secondary' => '220 14.3% 95.9%',
                'secondary-foreground' => '220.9 39.3% 11%',
                'muted' => '220 14.3% 95.9%',
                'muted-foreground' => '220 8.9% 46.1%',
                'accent' => '220 14.3% 95.9%',
                'accent-foreground' => '220.9 39.3% 11%',
                'border' => '220 13% 91%',
                'input' => '220 13% 91%',
                'ring' => '224 71.4% 4.1%',
                'sidebar-background' => '220 14.3% 97.9%',
                'sidebar-foreground' => '220 8.9% 36.1%',
                'sidebar-accent' => '220 14.3% 93.9%',
                'sidebar-accent-foreground' => '220.9 39.3% 31%',
                'sidebar-border' => '220 13% 91%',
            ],
            'dark' => [
                'background' => '224 71.4% 4.1%',
                'foreground' => '210 20% 98%',
                'card' => '224 71.4% 4.1%',
                'card-foreground' => '210 20% 98%',
                'popover' => '224 71.4% 4.1%',
                'popover-foreground' => '210 20% 98%',
                'primary' => '210 20% 98%',
                'primary-foreground' => '220.9 39.3% 11%',
                'secondary' => '215 27.9% 16.9%',
                'secondary-foreground' => '210 20% 98%',
                'muted' => '215 27.9% 16.9%',
                'muted-foreground' => '217.9 10.6% 64.9%',
                'accent' => '215 27.9% 16.9%',
                'accent-foreground' => '210 20% 98%',
                'border' => '215 27.9% 16.9%',
                'input' => '215 27.9% 16.9%',
                'ring' => '216 12.2% 83.9%',
                'sidebar-background' => '224 71.4% 7.1%',
                'sidebar-accent' => '215 27.9% 16.9%',
                'sidebar-accent-foreground' => '210 20% 98%',
                'sidebar-border' => '215 27.9% 16.9%',
            ],
        ],
    ];

    /**
     * The one colour the interface draws attention with.
     *
     * "default" is deliberately empty: it leaves the base colour's own primary
     * in place, which is the monochrome look the starter kit ships with.
     *
     * @var array<string, array{label: string, swatch: string, light: array<string, string>, dark: array<string, string>}>
     */
    private const ACCENTS = [
        'default' => [
            'label' => 'Default',
            'swatch' => '0 0% 9%',
            'light' => [],
            'dark' => [],
        ],
        'blue' => [
            'label' => 'Blue',
            'swatch' => '221.2 83.2% 53.3%',
            'light' => ['primary' => '221.2 83.2% 53.3%', 'primary-foreground' => '210 40% 98%', 'ring' => '221.2 83.2% 53.3%'],
            'dark' => ['primary' => '217.2 91.2% 59.8%', 'primary-foreground' => '222.2 47.4% 11.2%', 'ring' => '217.2 91.2% 59.8%'],
        ],
        'green' => [
            'label' => 'Green',
            'swatch' => '142.1 76.2% 36.3%',
            'light' => ['primary' => '142.1 76.2% 36.3%', 'primary-foreground' => '355.7 100% 97.3%', 'ring' => '142.1 76.2% 36.3%'],
            'dark' => ['primary' => '142.1 70.6% 45.3%', 'primary-foreground' => '144.9 80.4% 10%', 'ring' => '142.1 70.6% 45.3%'],
        ],
        'violet' => [
            'label' => 'Violet',
            'swatch' => '262.1 83.3% 57.8%',
            'light' => ['primary' => '262.1 83.3% 57.8%', 'primary-foreground' => '210 20% 98%', 'ring' => '262.1 83.3% 57.8%'],
            'dark' => ['primary' => '263.4 70% 50.4%', 'primary-foreground' => '210 20% 98%', 'ring' => '263.4 70% 50.4%'],
        ],
        'rose' => [
            'label' => 'Rose',
            'swatch' => '346.8 77.2% 49.8%',
            'light' => ['primary' => '346.8 77.2% 49.8%', 'primary-foreground' => '355.7 100% 97.3%', 'ring' => '346.8 77.2% 49.8%'],
            'dark' => ['primary' => '346.8 77.2% 49.8%', 'primary-foreground' => '355.7 100% 97.3%', 'ring' => '346.8 77.2% 49.8%'],
        ],
        'orange' => [
            'label' => 'Orange',
            'swatch' => '24.6 95% 53.1%',
            'light' => ['primary' => '24.6 95% 53.1%', 'primary-foreground' => '60 9.1% 97.8%', 'ring' => '24.6 95% 53.1%'],
            'dark' => ['primary' => '20.5 90.2% 48.2%', 'primary-foreground' => '60 9.1% 97.8%', 'ring' => '20.5 90.2% 48.2%'],
        ],
        'amber' => [
            'label' => 'Amber',
            'swatch' => '47.9 95.8% 53.1%',
            'light' => ['primary' => '35.5 91.7% 32.9%', 'primary-foreground' => '48 96% 89%', 'ring' => '35.5 91.7% 32.9%'],
            'dark' => ['primary' => '47.9 95.8% 53.1%', 'primary-foreground' => '26 83.3% 14.1%', 'ring' => '47.9 95.8% 53.1%'],
        ],
        'teal' => [
            'label' => 'Teal',
            'swatch' => '173 80% 40%',
            'light' => ['primary' => '173 80% 32%', 'primary-foreground' => '166 76% 97%', 'ring' => '173 80% 32%'],
            'dark' => ['primary' => '173 80% 40%', 'primary-foreground' => '170 84% 10%', 'ring' => '173 80% 40%'],
        ],
    ];

    /**
     * The base colours on offer, for the settings form.
     *
     * @return list<array{value: string, label: string, swatch: string}>
     */
    public function baseColors(): array
    {
        return $this->summarize(self::BASE_COLORS);
    }

    /**
     * The accents on offer.
     *
     * @return list<array{value: string, label: string, swatch: string}>
     */
    public function accents(): array
    {
        return $this->summarize(self::ACCENTS);
    }

    /**
     * The names a request may use, taken from the tables themselves.
     *
     * stylesheet() writes its values into the document as raw CSS, and what
     * keeps that safe is that a name never reaches it unless it is one of
     * these. A second list spelled out in the form request could drift from
     * this one; derived, it cannot.
     *
     * @return list<string>
     */
    public function names(string $group): array
    {
        return array_keys($group === 'accent' ? self::ACCENTS : self::BASE_COLORS);
    }

    /**
     * The stylesheet that turns the shipped palette into the chosen one.
     *
     * Empty when nothing was chosen, so the common case adds no bytes. The
     * accent is written after the base colour, because both name "--primary"
     * and the accent is the more specific choice.
     */
    public function stylesheet(string $baseColor, string $accent): string
    {
        return $this->rendered[$baseColor.'/'.$accent] ??= $this->render($baseColor, $accent);
    }

    /**
     * Build one.
     */
    private function render(string $baseColor, string $accent): string
    {
        $light = [
            ...self::BASE_COLORS[$baseColor]['light'] ?? [],
            ...self::ACCENTS[$accent]['light'] ?? [],
        ];

        $dark = [
            ...self::BASE_COLORS[$baseColor]['dark'] ?? [],
            ...self::ACCENTS[$accent]['dark'] ?? [],
        ];

        return $this->rule(':root', $light).$this->rule('.dark', $dark);
    }

    /**
     * One selector's worth of custom properties.
     *
     * @param  array<string, string>  $tokens
     */
    private function rule(string $selector, array $tokens): string
    {
        if ($tokens === []) {
            return '';
        }

        $declarations = '';

        foreach ($tokens as $token => $value) {
            /*
             * The token names are this class's own constants and the values
             * are HSL triplets from the same table, so nothing here comes from
             * a request. names() is what keeps that true: an unknown palette
             * name is refused before it reaches this method.
             */
            $declarations .= sprintf('--%s:hsl(%s);', $token, $value);
        }

        return $selector.'{'.$declarations.'}';
    }

    /**
     * A palette table reduced to what the form needs to draw a swatch.
     *
     * @param  array<string, array{label: string, swatch: string, light: array<string, string>, dark: array<string, string>}>  $palettes
     * @return list<array{value: string, label: string, swatch: string}>
     */
    private function summarize(array $palettes): array
    {
        $summary = [];

        foreach ($palettes as $value => $palette) {
            $summary[] = [
                'value' => $value,
                'label' => $palette['label'],
                'swatch' => 'hsl('.$palette['swatch'].')',
            ];
        }

        return $summary;
    }
}
