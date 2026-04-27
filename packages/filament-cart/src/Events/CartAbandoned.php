<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Events;

use AIArmada\FilamentCart\Models\Cart;

final readonly class CartAbandoned
{
    public function __construct(
        public string $sourceEventId,
        public string $cartId,
        public string $cartIdentifier,
        public string $cartInstance,
        public ?string $ownerType,
        public string | int | null $ownerId,
        public int $subtotalMinor,
        public int $totalMinor,
        public int $totalQuantity,
        public int $uniqueItemCount,
        public int $itemCount,
        public string $currency,
        public string $occurredAt,
    ) {}

    public static function fromCart(Cart $cart): self
    {
        $occurredAt = $cart->checkout_abandoned_at?->toAtomString() ?? now()->toAtomString();

        return new self(
            sourceEventId: sprintf('cart-abandoned:%s:%s', $cart->id, $cart->checkout_abandoned_at?->getTimestamp() ?? time()),
            cartId: (string) $cart->id,
            cartIdentifier: $cart->identifier,
            cartInstance: $cart->instance,
            ownerType: $cart->owner_type,
            ownerId: $cart->owner_id,
            subtotalMinor: $cart->subtotal,
            totalMinor: $cart->total,
            totalQuantity: $cart->quantity,
            uniqueItemCount: $cart->items_count,
            itemCount: $cart->items_count,
            currency: $cart->currency,
            occurredAt: $occurredAt,
        );
    }
}
