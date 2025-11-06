<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

/**
 * Provides Heroicon icons for enum cases.
 *
 * Use this trait in your enums to define icons for display
 * in Filament tables, forms, and other UI components.
 *
 * Example:
 * ```
 * enum ShippingStatus: string
 * {
 *     use HasIcons;
 *
 *     case PENDING = 'pending';
 *     case IN_TRANSIT = 'in_transit';
 *     case DELIVERED = 'delivered';
 *
 *     public function getIcon(): string
 *     {
 *         return match($this) {
 *             self::PENDING => 'heroicon-o-clock',
 *             self::IN_TRANSIT => 'heroicon-o-truck',
 *             self::DELIVERED => 'heroicon-o-check-circle',
 *         };
 *     }
 * }
 * ```
 */
/**
 * @phpstan-ignore trait.unused
 */
trait HasIcons
{
    /**
     * Get the Heroicon icon name for this enum case.
     *
     * Use Heroicon v2 naming convention:
     * - Outline: heroicon-o-{name}
     * - Solid: heroicon-s-{name}
     * - Mini: heroicon-m-{name}
     */
    abstract public function getIcon(): string;

    /**
     * Get all icons as an associative array.
     *
     * @return array<string, string> Array of [value => icon]
     */
    public static function icons(): array
    {
        $icons = [];

        foreach (self::cases() as $case) {
            $icons[$case->value] = $case->getIcon();
        }

        return $icons;
    }

    /**
     * Get an icon by value.
     *
     * @param  string|int  $value  The enum value
     * @return string|null The icon name, or null if not found
     */
    public static function getIconByValue(string|int $value): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case->getIcon();
            }
        }

        return null;
    }

    /**
     * Get icon for Filament display.
     *
     * Alias for getIcon() for Filament components.
     */
    public function getFilamentIcon(): string
    {
        return $this->getIcon();
    }
}
