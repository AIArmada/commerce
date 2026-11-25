<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventOrderItemData
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $id,
        public string $orderId,
        public string $orderClass,
        public string $orderItemClass,
        public ?string $ticketTypeId,
        public int $quantity,
        public int $unitPrice,
        public int $total,
        public int $discountAmount = 0,
        public int $taxAmount = 0,
        public ?string $currency = null,
        public array $options = [],
    ) {}
}
