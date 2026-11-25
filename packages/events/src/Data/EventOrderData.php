<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventOrderData
{
    /**
     * @param  list<EventOrderItemData>  $items
     */
    public function __construct(
        public string $id,
        public string $orderClass,
        public ?string $customerId,
        public ?string $customerName,
        public ?string $customerEmail,
        public ?string $customerPhone,
        public ?string $customerType = null,
        public array $items = [],
    ) {}
}
