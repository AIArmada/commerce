<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

/**
 * Checkout view of a cart item.
 *
 * Item-level conditions are already baked into the item price, so the
 * discount is reported as zero here and the tax percent is zeroed whenever
 * a tax condition is present: cart-level adjustments surface on the cart's
 * own discount and tax terms instead of being double-counted per line (a
 * gateway receiving the baked price plus a nonzero tax percent would apply
 * the tax a second time). An attribute-declared tax rate touches neither
 * the price nor the cart tax term, so it passes through for the gateway
 * to apply.
 */
final readonly class CartCheckoutLineItem implements LineItemInterface
{
    public function __construct(private CartItem $item) {}

    public function getLineItemId(): string
    {
        return $this->item->getLineItemId();
    }

    public function getLineItemName(): string
    {
        return $this->item->getLineItemName();
    }

    public function getLineItemPrice(): Money
    {
        return $this->item->getLineItemPrice();
    }

    public function getLineItemQuantity(): int | float
    {
        return $this->item->getLineItemQuantity();
    }

    public function getLineItemDiscount(): Money
    {
        return new Money(0, $this->item->getLineItemPrice()->getCurrency(), false);
    }

    public function getLineItemTaxPercent(): float
    {
        foreach ($this->item->getConditions() as $condition) {
            if ($condition->getType() === 'tax') {
                return 0.0;
            }
        }

        return $this->item->getLineItemTaxPercent();
    }

    public function getLineItemSubtotal(): Money
    {
        return new Money(
            (int) $this->item->getLineItemPrice()->getAmount() * $this->item->getLineItemQuantity(),
            $this->item->getLineItemPrice()->getCurrency(),
            false
        );
    }

    public function getLineItemCategory(): ?string
    {
        return $this->item->getLineItemCategory();
    }

    /**
     * @return array<string, mixed>
     */
    public function getLineItemMetadata(): array
    {
        return $this->item->getLineItemMetadata();
    }
}
