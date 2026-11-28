<?php

declare(strict_types=1);

namespace AIArmada\Orders\Data;

final readonly class ReturnedPackageUnit
{
    public function __construct(
        public string $orderItemId,
        public int $ordinal,
    ) {}
}
