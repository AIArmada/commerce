<?php

declare(strict_types=1);

namespace AIArmada\Orders\Data;

final readonly class OrderIntakeData
{
    public function __construct(
        public string $id,
        public ?string $orderNumber,
        public int $grandTotal,
        public ?string $currency,
        public int $paidTotal,
    ) {}
}
