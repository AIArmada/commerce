<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

/**
 * Provides human-readable labels for enum cases.
 *
 * Use this trait in your enums to provide user-friendly labels
 * for display in UIs, forms, and reports.
 *
 * Example:
 * ```
 * enum OrderStatus: string
 * {
 *     use HasLabels;
 *
 *     case PENDING = 'pending';
 *     case PAID = 'paid';
 *     case SHIPPED = 'shipped';
 *
 *     public function getLabel(): string
 *     {
 *         return match($this) {
 *             self::PENDING => 'Pending Payment',
 *             self::PAID => 'Paid',
 *             self::SHIPPED => 'Shipped',
 *         };
 *     }
 * }
 * ```
 */
/**
 * @phpstan-ignore trait.unused
 */
trait HasLabels
{
    /**
     * Get the human-readable label for this enum case.
     */
    abstract public function getLabel(): string;

    /**
     * Get all labels as an associative array.
     *
     * @return array<string, string> Array of [value => label]
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->getLabel();
        }

        return $labels;
    }

    /**
     * Get options array for select inputs.
     *
     * Returns array suitable for Filament Select components and HTML selects.
     *
     * @return array<string, string> Array of [value => label]
     */
    public static function toSelectOptions(): array
    {
        return self::labels();
    }

    /**
     * Get a label by value.
     *
     * @param  string|int  $value  The enum value
     * @return string|null The label, or null if not found
     */
    public static function getLabelByValue(string|int $value): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case->getLabel();
            }
        }

        return null;
    }

    /**
     * Find an enum case by its label (case-insensitive search).
     *
     * @param  string  $label  The label to search for
     * @return static|null The matching enum case, or null if not found
     */
    public static function fromLabel(string $label): ?static
    {
        $label = mb_strtolower($label);

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->getLabel()) === $label) {
                return $case;
            }
        }

        return null;
    }
}
