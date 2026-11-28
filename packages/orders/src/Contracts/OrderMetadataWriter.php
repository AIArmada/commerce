<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderMetadataWriter
{
    /** @param array<string, mixed> $patch */
    public function merge(string $orderId, array $patch): void;
}
