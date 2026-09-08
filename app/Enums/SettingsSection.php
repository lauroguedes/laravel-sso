<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The tabs the application settings are divided into.
 *
 * A section is not decoration: it decides which rules a request is validated
 * against, which settings that request may change, and where a save returns
 * to. Naming them once means the strip of tabs, the validation, and the
 * redirect cannot disagree about what sections exist.
 */
enum SettingsSection: string
{
    /** What this server calls itself, and the mark it shows. */
    case Brand = 'brand';

    /** The colours the interface is drawn in. */
    case Appearance = 'appearance';

    /** Which shell the administration and the sign-in page use. */
    case Layout = 'layout';

    /** The reference links shown beneath the menu. */
    case Links = 'links';

    /** Who may sign up, and how long anything they are issued lasts. */
    case Access = 'access';

    /**
     * The section shown when nothing names one.
     */
    public static function default(): self
    {
        return self::Brand;
    }

    /**
     * The section a request named, or the default if it named nothing valid.
     */
    public static function fromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::default()) : self::default();
    }

    /**
     * The tab's label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Brand => 'Brand',
            self::Appearance => 'Appearance',
            self::Layout => 'Layout',
            self::Links => 'Links',
            self::Access => 'Access',
        };
    }

    /**
     * Every section, as the tab strip renders them.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $section): array => ['value' => $section->value, 'label' => $section->label()],
            self::cases(),
        );
    }
}
