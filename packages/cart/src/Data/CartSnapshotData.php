<?php

declare(strict_types=1);

namespace AIArmada\Cart\Data;

use DateTimeImmutable;

final readonly class CartSnapshotData
{
    public function __construct(
        public string $id,
        public string $identifier,
        public string $instance,
        public ?string $ownerType,
        public string | int | null $ownerId,
        public int $subtotal,
        public int $total,
        public int $quantity,
        public int $itemsCount,
        public string $currency,
        public ?DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $checkoutStartedAt,
        public ?DateTimeImmutable $checkoutAbandonedAt,
    ) {}
}
