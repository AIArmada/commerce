<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Data\CartSnapshotData;
use Carbon\CarbonImmutable;

final readonly class CartCheckoutStarted
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

    public static function fromSnapshot(CartSnapshotData $cart): self
    {
        $occurredAt = $cart->checkoutStartedAt?->format(DATE_ATOM) ?? CarbonImmutable::now()->toAtomString();

        return new self(
            sourceEventId: sprintf('cart-checkout-started:%s:%s', $cart->id, $cart->checkoutStartedAt?->getTimestamp() ?? time()),
            cartId: (string) $cart->id,
            cartIdentifier: $cart->identifier,
            cartInstance: $cart->instance,
            ownerType: $cart->ownerType,
            ownerId: $cart->ownerId,
            subtotalMinor: $cart->subtotal,
            totalMinor: $cart->total,
            totalQuantity: $cart->quantity,
            uniqueItemCount: $cart->itemsCount,
            itemCount: $cart->itemsCount,
            currency: $cart->currency,
            occurredAt: $occurredAt,
        );
    }
}
