<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\Cart\Contracts\BuyablePriceResolver;
use AIArmada\Cart\Support\BuyablePriceData;
use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\CommerceSupport\Contracts\Priceable;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;

/**
 * Quotes catalog cart lines through the canonical pricing calculator.
 */
final class CartBuyablePriceResolver implements BuyablePriceResolver
{
    public function __construct(
        private readonly PriceCalculatorInterface $calculator,
    ) {}

    public function resolve(Buyable $buyable, int $quantity, array $context = []): BuyablePriceData
    {
        if (! $buyable instanceof Priceable) {
            return new BuyablePriceData(
                unitPrice: $buyable->getBuyablePrice(),
                currency: $buyable->getBuyableCurrency(),
            );
        }

        $result = $this->calculator->calculate($buyable, $quantity, $context);

        return new BuyablePriceData(
            unitPrice: $result->finalPrice,
            currency: $result->currency,
            originalUnitPrice: $result->originalPrice,
            discountAmount: $result->discountAmount,
            discountSource: $result->discountSource,
            priceListName: $result->priceListName,
            tierDescription: $result->tierDescription,
            promotionName: $result->promotionName,
            breakdown: $result->breakdown,
        );
    }
}
