<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

/**
 * Provides optional descriptions for enum cases.
 *
 * Use this trait in your enums to add detailed descriptions
 * for tooltips, help text, and documentation.
 *
 * Example:
 * ```
 * enum VoucherType: string
 * {
 *     use HasDescriptions;
 *
 *     case PERCENTAGE = 'percentage';
 *     case FIXED = 'fixed';
 *     case FREE_SHIPPING = 'free_shipping';
 *
 *     public function getDescription(): ?string
 *     {
 *         return match($this) {
 *             self::PERCENTAGE => 'Discount applied as a percentage of cart total',
 *             self::FIXED => 'Fixed amount discount on cart total',
 *             self::FREE_SHIPPING => 'Removes shipping cost from the order',
 *         };
 *     }
 * }
 * ```
 */
/**
 * @phpstan-ignore trait.unused
 */
trait HasDescriptions
{
    /**
     * Get the description for this enum case.
     *
     * Return null if no description is needed.
     */
    abstract public function getDescription(): ?string;

    /**
     * Get all descriptions as an associative array.
     *
     * @return array<string, string|null> Array of [value => description]
     */
    public static function descriptions(): array
    {
        $descriptions = [];

        foreach (self::cases() as $case) {
            $descriptions[$case->value] = $case->getDescription();
        }

        return $descriptions;
    }

    /**
     * Get a description by value.
     *
     * @param  string|int  $value  The enum value
     * @return string|null The description, or null if not found
     */
    public static function getDescriptionByValue(string|int $value): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case->getDescription();
            }
        }

        return null;
    }

    /**
     * Check if this enum case has a description.
     */
    public function hasDescription(): bool
    {
        return $this->getDescription() !== null;
    }
}
