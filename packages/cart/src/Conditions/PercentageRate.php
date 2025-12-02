<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use InvalidArgumentException;

/**
 * Represents a percentage rate using basis points for integer-only arithmetic.
 *
 * 1 basis point (bp) = 0.01% = 1/10000
 * Examples: 10% = 1000bp, 10.50% = 1050bp, 0.25% = 25bp
 */
final readonly class PercentageRate
{
    /**
     * Scale factor for basis points (1% = 100 basis points).
     */
    public const SCALE = 10000;

    /**
     * @param  int  $basisPoints  The percentage in basis points (e.g., 1000 = 10%)
     */
    public function __construct(
        public int $basisPoints
    ) {}

    /**
     * Create from a percentage string like "-10.5%", "+8%", "12.5%", or "%10".
     */
    public static function fromPercentString(string $value): self
    {
        $value = mb_trim($value);

        // Handle %10 format (prefix)
        if (str_starts_with($value, '%')) {
            $numericPart = mb_substr($value, 1);

            if (! is_numeric($numericPart)) {
                throw new InvalidCartConditionException("Invalid percentage value: {$value}");
            }

            $floatValue = (float) $numericPart;

            if (! is_finite($floatValue)) {
                throw new InvalidCartConditionException("Invalid percentage value: {$value}");
            }

            // Positive percentage
            return new self((int) round($floatValue * 100));
        }

        // Handle 10% format (suffix)
        if (! str_ends_with($value, '%')) {
            throw new InvalidCartConditionException("Percentage value must contain '%': {$value}");
        }

        $numericPart = mb_substr($value, 0, -1);

        // Handle operators
        $sign = 1;
        if (str_starts_with($numericPart, '-')) {
            $sign = -1;
            $numericPart = mb_substr($numericPart, 1);
        } elseif (str_starts_with($numericPart, '+')) {
            $numericPart = mb_substr($numericPart, 1);
        }

        if (! is_numeric($numericPart)) {
            throw new InvalidCartConditionException("Invalid percentage value: {$value}");
        }

        $floatValue = (float) $numericPart;

        if (! is_finite($floatValue)) {
            throw new InvalidCartConditionException("Invalid percentage value: {$value}");
        }

        // Convert to basis points: 10.50% → 1050
        // Multiply by 100 to get basis points (since 1% = 100bp)
        $basisPoints = (int) round($floatValue * 100) * $sign;

        return new self($basisPoints);
    }

    /**
     * Create from an integer representing basis points.
     */
    public static function fromBasisPoints(int $basisPoints): self
    {
        return new self($basisPoints);
    }

    /**
     * Create from a decimal rate (e.g., 0.10 for 10%).
     */
    public static function fromDecimal(float $decimal): self
    {
        return new self((int) round($decimal * self::SCALE));
    }

    /**
     * Apply this percentage to an amount and return the adjustment (not the new total).
     *
     * @param  int  $amountCents  The base amount in cents
     * @param  string  $roundingMode  Rounding mode: half_up, half_even, floor, ceil
     * @return int The adjustment amount (positive for additions, negative for discounts)
     */
    public function calculateAdjustment(int $amountCents, string $roundingMode = 'half_up'): int
    {
        $raw = $amountCents * $this->basisPoints;

        return $this->roundDivide($raw, self::SCALE, $roundingMode);
    }

    /**
     * Apply this percentage to an amount and return the new total.
     *
     * @param  int  $amountCents  The base amount in cents
     * @param  string  $roundingMode  Rounding mode: half_up, half_even, floor, ceil
     * @return int The new total after applying the percentage
     */
    public function apply(int $amountCents, string $roundingMode = 'half_up'): int
    {
        $adjustment = $this->calculateAdjustment($amountCents, $roundingMode);

        return $amountCents + $adjustment;
    }

    /**
     * Get the decimal representation (for display purposes only).
     */
    public function toDecimal(): float
    {
        return $this->basisPoints / self::SCALE;
    }

    /**
     * Get the percentage value (for display purposes only).
     * e.g., 1050bp → 10.50
     */
    public function toPercent(): float
    {
        return $this->basisPoints / 100;
    }

    /**
     * Convert to a display string like "10.50%", "-10.50%".
     */
    public function toPercentString(bool $includeSign = false): string
    {
        $percent = abs($this->basisPoints) / 100;
        $formatted = number_format($percent, 2, '.', '');

        if ($this->basisPoints < 0) {
            return '-'.$formatted.'%';
        }

        if ($includeSign && $this->basisPoints > 0) {
            return '+'.$formatted.'%';
        }

        return $formatted.'%';
    }

    /**
     * Check if this is a discount (negative percentage).
     */
    public function isDiscount(): bool
    {
        return $this->basisPoints < 0;
    }

    /**
     * Check if this is a charge (positive percentage).
     */
    public function isCharge(): bool
    {
        return $this->basisPoints > 0;
    }

    /**
     * Check if this is zero.
     */
    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }

    /**
     * Perform integer division with specified rounding mode.
     */
    private function roundDivide(int $numerator, int $denominator, string $mode): int
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Division by zero');
        }

        return match ($mode) {
            'half_up' => $this->roundHalfUp($numerator, $denominator),
            'half_even' => $this->roundHalfEven($numerator, $denominator),
            'floor' => intdiv($numerator, $denominator),
            'ceil' => (int) ceil($numerator / $denominator),
            default => $this->roundHalfUp($numerator, $denominator),
        };
    }

    /**
     * Round half up (commercial rounding).
     */
    private function roundHalfUp(int $numerator, int $denominator): int
    {
        $sign = ($numerator >= 0) === ($denominator >= 0) ? 1 : -1;
        $absNum = abs($numerator);
        $absDen = abs($denominator);

        return $sign * intdiv($absNum + intdiv($absDen, 2), $absDen);
    }

    /**
     * Round half to even (banker's rounding).
     */
    private function roundHalfEven(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;
        $halfDenom = intdiv($denominator, 2);

        // Exactly half?
        if (abs($remainder) === $halfDenom) {
            // Round to even
            return ($quotient % 2 === 0) ? $quotient : $quotient + ($numerator >= 0 ? 1 : -1);
        }

        // Not exactly half, use standard rounding
        if (abs($remainder) > $halfDenom) {
            return $quotient + ($numerator >= 0 ? 1 : -1);
        }

        return $quotient;
    }
}
