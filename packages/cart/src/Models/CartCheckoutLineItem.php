<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

/**
 * Checkout view of a cart item.
 *
 * Item-level conditions are already baked into the item price, so the
 * discount is reported as zero here: cart-level adjustments surface on the
 * cart's own discount term instead of being double-counted per line.
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
