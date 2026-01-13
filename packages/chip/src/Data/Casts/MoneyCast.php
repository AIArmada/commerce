<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data\Casts;

use Akaunting\Money\Money;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Casts\Uncastable;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Cast integer cents from CHIP API to Akaunting Money object.
 *
 * Usage with #[WithCast] attribute:
 *   #[WithCast(MoneyCast::class, currency: 'MYR')]
 *   public Money $amount;
 *
 * Or with currency from another property:
 *   #[WithCast(MoneyCast::class, currencyProperty: 'currency')]
 *   public Money $amount;
 */
final class MoneyCast implements Cast
{
    public function __construct(
        private readonly ?string $currency = null,
        private readonly ?string $currencyProperty = null,
    ) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Money | Uncastable
    {
        if ($value instanceof Money) {
            return $value;
        }

        if ($value === null) {
            return Uncastable::create();
        }

        $currency = $this->resolveCurrency($properties);

        if (! is_numeric($value)) {
            return Uncastable::create();
        }

        return Money::{$currency}((int) $value);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function resolveCurrency(array $properties): string
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        if ($this->currencyProperty !== null && isset($properties[$this->currencyProperty])) {
            return (string) $properties[$this->currencyProperty];
        }

        return $properties['currency'] ?? 'MYR';
    }
}
