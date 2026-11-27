<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Data\CartSnapshotData;
use Carbon\CarbonImmutable;

final readonly class CartSnapshotSynced
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
        return new self(
            sourceEventId: sprintf('cart-snapshot-synced:%s:%s', $cart->id, $cart->updatedAt?->getTimestamp() ?? time()),
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
            occurredAt: CarbonImmutable::now()->toAtomString(),
        );
    }
}
