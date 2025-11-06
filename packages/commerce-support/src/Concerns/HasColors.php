<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

/**
 * @phpstan-ignore trait.unused
 */
trait HasColors
{
    /**
     * Get the Filament color for this enum case.
     *
     * Common colors: primary, secondary, success, warning, danger, info, gray
     */
    abstract public function getColor(): string;

    /**
     * Get all colors as an associative array.
     *
     * @return array<string, string> Array of [value => color]
     */
    public static function colors(): array
    {
        $colors = [];

        foreach (self::cases() as $case) {
            $colors[$case->value] = $case->getColor();
        }

        return $colors;
    }

    /**
     * Get a color by value.
     *
     * @param  string|int  $value  The enum value
     * @return string|null The color, or null if not found
     */
    public static function getColorByValue(string|int $value): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case->getColor();
            }
        }

        return null;
    }

    /**
     * Get color for Filament badge.
     *
     * Alias for getColor() for Filament components.
     */
    public function getBadgeColor(): string
    {
        return $this->getColor();
    }
}
