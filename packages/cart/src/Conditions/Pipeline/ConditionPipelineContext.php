<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Models\CartItem;

final class ConditionPipelineContext
{
    private ?int $initialAmount = null;

    public function __construct(
        private Cart $cart,
        private ?CartConditionCollection $conditions = null,
        ?int $initialAmount = null
    ) {
        $this->conditions ??= $cart->getConditions();
        $this->initialAmount = $initialAmount;
    }

    public static function fromCart(Cart $cart, ?int $initialAmount = null): self
    {
        return new self($cart, null, $initialAmount);
    }

    public function cart(): Cart
    {
        return $this->cart;
    }

    public function conditions(): CartConditionCollection
    {
        return $this->conditions ?? $this->cart->getConditions();
    }

    public function initialAmount(): int
    {
        if ($this->initialAmount !== null) {
            return $this->initialAmount;
        }

        $items = $this->cart->getItems();
        $this->initialAmount = (int) $items->sum(
            static fn (CartItem $item) => $item->getRawSubtotal()
        );

        return $this->initialAmount;
    }

    public function cartHasShipmentsResolver(): bool
    {
        /** @phpstan-ignore-next-line */
        return method_exists($this->cart, 'hasShipmentResolver') && $this->cart->hasShipmentResolver();
    }

    /**
     * @return iterable<mixed>
     */
    public function getShipments(): iterable
    {
        if (! $this->cartHasShipmentsResolver()) {
            return [];
        }

        return $this->cart->getShipments();
    }

    public function cartHasPaymentsResolver(): bool
    {
        /** @phpstan-ignore-next-line */
        return method_exists($this->cart, 'hasPaymentResolver') && $this->cart->hasPaymentResolver();
    }

    /**
     * @return iterable<mixed>
     */
    public function getPayments(): iterable
    {
        if (! $this->cartHasPaymentsResolver()) {
            return [];
        }

        return $this->cart->getPayments();
    }

    public function cartHasFulfillmentResolver(): bool
    {
        /** @phpstan-ignore-next-line */
        return method_exists($this->cart, 'hasFulfillmentResolver') && $this->cart->hasFulfillmentResolver();
    }

    /**
     * @return iterable<mixed>
     */
    public function getFulfillments(): iterable
    {
        if (! $this->cartHasFulfillmentResolver()) {
            return [];
        }

        return $this->cart->getFulfillments();
    }
}
