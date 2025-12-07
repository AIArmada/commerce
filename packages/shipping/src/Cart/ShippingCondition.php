<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Cart;

use AIArmada\Cart\Conditions\Condition;

/**
 * Shipping condition applied to the cart.
 */
class ShippingCondition extends Condition
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        string $name,
        string $type,
        int|float|string $value,
        array $attributes = []
    ) {
        parent::__construct($name, $type, $value, $attributes);
    }

    public function getCarrier(): ?string
    {
        return $this->getAttribute('carrier');
    }

    public function getService(): ?string
    {
        return $this->getAttribute('service');
    }

    public function getEstimatedDays(): ?int
    {
        return $this->getAttribute('estimated_days');
    }

    public function getQuoteId(): ?string
    {
        return $this->getAttribute('quote_id');
    }

    public function isFreeShipping(): bool
    {
        return $this->getValue() === 0;
    }

    public function getFormattedValue(): string
    {
        if ($this->isFreeShipping()) {
            return 'FREE';
        }

        $value = $this->getValue();
        $currency = $this->getAttribute('currency') ?? 'MYR';

        return number_format($value / 100, 2).' '.$currency;
    }
}
