<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

interface UpdateTrackingSnapshot
{
    /** @param array<string, mixed> $attributes */
    public function handle(string $orderId, array $attributes): void;
}
